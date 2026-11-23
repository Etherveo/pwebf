<?php

use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentStatusTransition;
use App\Models\Product;
use App\Models\User;
use App\Payments\SignatureVerifier;
use Carbon\Carbon;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    config()->set('payment.driver', 'manual');
    config()->set('payment.webhook_secret', 'test-secret');
    config()->set('app.url', 'http://localhost:8000');
});

function m3cBuyer(): User
{
    return User::factory()->create();
}

function m3cAdmin(): User
{
    return User::factory()->admin()->create();
}

function m3cProduct(array $attributes = []): Product
{
    return Product::factory()->create(array_merge(
        ['stock_on_hand' => 10, 'stock_reserved' => 0, 'price_idr' => 5000],
        $attributes,
    ));
}

function m3cPickup(): array
{
    return [
        'pickup_date' => Carbon::now('Asia/Makassar')->addDay()->toDateString(),
        'pickup_time' => '10:00',
    ];
}

function m3cCheckout($t, User $buyer, array $lines, string $method = 'qris', ?string $key = null): Order
{
    $t->actingAs($buyer, 'web');

    foreach ($lines as $line) {
        $t->post('/keranjang/tambah', [
            'product_id' => $line['product']->id,
            'quantity' => $line['qty'],
        ])->assertRedirect('/keranjang');
    }

    $t->post('/checkout', [
        'payment_method' => $method,
        'checkout_key' => $key ?? (string) Str::uuid(),
        ...m3cPickup(),
    ])->assertRedirect();

    return Order::where('user_id', $buyer->id)->latest('id')->first();
}

function m3cWebhook($t, Payment $payment, string $status): TestResponse
{
    $payload = [
        'payment_id' => $payment->id,
        'order_id' => $payment->order->order_number,
        'status' => $status,
        'reference' => $payment->reference_code,
    ];
    if ($status === 'paid') {
        $payload['paid_at'] = now()->toIso8601String();
    }

    $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    return $t->call('POST', '/api/v1/webhooks/payments/manual', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
        'HTTP_X_WEBHOOK_SIGNATURE' => SignatureVerifier::sign($body, 'test-secret'),
    ], $body);
}

function m3cPaidOrder($t, User $buyer): Order
{
    $order = m3cCheckout($t, $buyer, [['product' => m3cProduct(), 'qty' => 1]]);
    $payment = $order->payments()->first();
    m3cWebhook($t, $payment, 'paid')->assertOk();

    return $order->fresh();
}

// ---- Timeline & Clean Payment Detail ----

test('PAY-39 timeline shows transitions chronologically in UI and records trigger + request id', function () {
    $buyer = m3cBuyer();
    $order = m3cPaidOrder($this, $buyer);
    $payment = $order->payments()->first();

    $paidTransition = PaymentStatusTransition::query()
        ->where('payment_id', $payment->id)->where('to_status', 'paid')->first();
    expect($paidTransition)->not->toBeNull();
    expect($paidTransition->request_id)->not->toBeNull();
    expect($paidTransition->trigger)->toBe('callback_gateway');

    $this->actingAs($buyer, 'web');
    $this->get('/pembayaran/'.$payment->id)->assertOk()
        ->assertSee('Timeline Transisi Status')
        ->assertSee('Menunggu Pembayaran')
        ->assertSee('Terbayar')
        ->assertSee('WITA')
        ->assertDontSee('Simulated HTTP Request')
        ->assertDontSee('SIMULASI')
        ->assertDontSee('test-secret');
});

test('PAY-42/43 detail page renders transition timeline cleanly and has no payment-mutation control', function () {
    $buyer = m3cBuyer();
    $order = m3cCheckout($this, $buyer, [['product' => m3cProduct(), 'qty' => 1]]);
    $payment = $order->payments()->first();

    PaymentStatusTransition::factory()->create([
        'payment_id' => $payment->id,
        'from_status' => 'pending',
        'to_status' => 'paid',
        'trigger' => 'callback_gateway',
        'occurred_at' => now(),
    ]);

    $this->actingAs($buyer, 'web');
    $this->get('/pembayaran/'.$payment->id)->assertOk()
        ->assertSee('Timeline Transisi Status')
        ->assertSee('Terbayar')
        ->assertDontSee('panel developer')
        ->assertDontSee('Ubah status pembayaran')
        ->assertDontSee('name="status"', false);
});

// ---- Result pages + polling: PAY-13/45/46/47 ----

test('PAY-45 result page for a paid payment shows the summary and links to order detail and receipt', function () {
    $buyer = m3cBuyer();
    $order = m3cPaidOrder($this, $buyer);
    $payment = $order->payments()->first();

    $this->actingAs($buyer, 'web');
    $this->get('/pembayaran/'.$payment->id.'/hasil')->assertOk()
        ->assertSee('Pembayaran Berhasil')
        ->assertDontSee('SIMULASI')
        ->assertSee($order->order_number)
        ->assertSee($order->formattedTotal())
        ->assertSee('Lihat detail pesanan')
        ->assertSee('Lihat struk');
});

test('PAY-46 failed result page offers repay with another method and cancel while allowed', function () {
    $buyer = m3cBuyer();
    $order = m3cCheckout($this, $buyer, [['product' => m3cProduct(), 'qty' => 1]]);
    $payment = $order->payments()->first();
    m3cWebhook($this, $payment, 'failed')->assertOk();

    $this->actingAs($buyer, 'web');
    $this->get('/pembayaran/'.$payment->id.'/hasil')->assertOk()
        ->assertSee('Pembayaran Gagal')
        ->assertDontSee('SIMULASI')
        ->assertSee($order->order_number)
        ->assertSee('Buat Pembayaran Baru')
        ->assertSee('Batalkan Pesanan')
        ->assertSee('name="payment_method"', false);
});

test('PAY-46 failed result page after the deadline offers no repay or cancel', function () {
    $buyer = m3cBuyer();
    $order = m3cCheckout($this, $buyer, [['product' => m3cProduct(), 'qty' => 1]]);
    $payment = $order->payments()->first();
    m3cWebhook($this, $payment, 'failed')->assertOk();

    try {
        Carbon::setTestNow(now()->addMinutes(31));

        $this->actingAs($buyer, 'web');
        $this->get('/pembayaran/'.$payment->id.'/hasil')->assertOk()
            ->assertSee('Pembayaran Gagal')
            ->assertDontSee('Buat Pembayaran Baru')
            ->assertDontSee('Batalkan Pesanan');
    } finally {
        Carbon::setTestNow();
    }
});

test('PAY-47 expired result page has no pay action and links to order history and catalog', function () {
    $buyer = m3cBuyer();
    $order = m3cCheckout($this, $buyer, [['product' => m3cProduct(), 'qty' => 1]]);
    $payment = $order->payments()->first();

    try {
        Carbon::setTestNow(now()->addMinutes(31));

        $this->actingAs($buyer, 'web');
        $this->get('/pembayaran/'.$payment->id.'/hasil')->assertOk()
            ->assertSee('Pembayaran Kedaluwarsa')
            ->assertDontSee('SIMULASI')
            ->assertSee($order->order_number)
            ->assertDontSee('Buat Pembayaran Baru')
            ->assertSee('Lihat riwayat pesanan')
            ->assertSee('Kembali ke katalog');
    } finally {
        Carbon::setTestNow();
    }
});

test('PAY-45/47 result page redirects a pending payment to the instruction page and a cancelled one to the order detail', function () {
    $buyer = m3cBuyer();
    $order = m3cCheckout($this, $buyer, [['product' => m3cProduct(), 'qty' => 1]]);
    $payment = $order->payments()->first();

    $this->actingAs($buyer, 'web');
    $this->get('/pembayaran/'.$payment->id.'/hasil')->assertRedirect(route('pesanan.bayar', $order));

    $this->post('/pesanan/'.$order->id.'/batal')->assertRedirect(route('pesanan.show', $order));
    $this->get('/pembayaran/'.$payment->id.'/hasil')->assertRedirect(route('pesanan.show', $order));
});

test('PAY-13 polling redirects to the result page for paid/failed/expired and to the order detail for cancelled', function () {
    $buyer = m3cBuyer();

    $order = m3cCheckout($this, $buyer, [['product' => m3cProduct(), 'qty' => 1]]);
    $payment = $order->payments()->first();

    $this->actingAs($buyer, 'web');
    $this->getJson('/pembayaran/'.$payment->id.'/status')->assertOk()
        ->assertJsonPath('redirect_url', null);

    m3cWebhook($this, $payment, 'paid')->assertOk();
    $this->getJson('/pembayaran/'.$payment->id.'/status')->assertOk()
        ->assertJsonPath('redirect_url', route('pembayaran.hasil', $payment));

    $order2 = m3cCheckout($this, $buyer, [['product' => m3cProduct(), 'qty' => 1]]);
    $failed = $order2->payments()->first();
    m3cWebhook($this, $failed, 'failed')->assertOk();
    $this->getJson('/pembayaran/'.$failed->id.'/status')->assertOk()
        ->assertJsonPath('redirect_url', route('pembayaran.hasil', $failed));

    $order3 = m3cCheckout($this, $buyer, [['product' => m3cProduct(), 'qty' => 1]]);
    $expired = $order3->payments()->first();

    try {
        Carbon::setTestNow(now()->addMinutes(31));
        $this->getJson('/pembayaran/'.$expired->id.'/status')->assertOk()
            ->assertJson(['status' => 'expired'])
            ->assertJsonPath('redirect_url', route('pembayaran.hasil', $expired));
    } finally {
        Carbon::setTestNow();
    }

    $order4 = m3cCheckout($this, $buyer, [['product' => m3cProduct(), 'qty' => 1]]);
    $cancelled = $order4->payments()->first();
    $this->post('/pesanan/'.$order4->id.'/batal')->assertRedirect(route('pesanan.show', $order4));
    $this->getJson('/pembayaran/'.$cancelled->id.'/status')->assertOk()
        ->assertJson(['status' => 'cancelled'])
        ->assertJsonPath('redirect_url', route('pesanan.show', $order4));
});

// ---- Receipt: PAY-48 ----

test('PAY-48 receipt for a paid payment shows items, total, method and paid time cleanly', function () {
    $buyer = m3cBuyer();
    $order = m3cCheckout($this, $buyer, [['product' => m3cProduct(['name' => 'Pulpen Struk']), 'qty' => 2]]);
    $payment = $order->payments()->first();
    m3cWebhook($this, $payment, 'paid')->assertOk();

    $this->actingAs($buyer, 'web');
    $this->get('/pembayaran/'.$payment->id.'/struk')->assertOk()
        ->assertSee('Struk Pembayaran')
        ->assertDontSee('SIMULASI')
        ->assertSee('Cetak Struk')
        ->assertSee($order->order_number)
        ->assertSee('Pulpen Struk')
        ->assertSee($order->formattedTotal())
        ->assertSee('QRIS');
});

test('PAY-48 receipt redirects a non-paid payment to its result page and is owner-only', function () {
    $buyer = m3cBuyer();
    $order = m3cCheckout($this, $buyer, [['product' => m3cProduct(), 'qty' => 1]]);
    $payment = $order->payments()->first();

    $this->actingAs($buyer, 'web');
    $this->get('/pembayaran/'.$payment->id.'/struk')->assertRedirect(route('pembayaran.hasil', $payment));

    $this->actingAs(m3cBuyer(), 'web');
    $this->get('/pembayaran/'.$payment->id.'/struk')->assertNotFound();

    $this->post('/keluar');
    $this->get('/pembayaran/'.$payment->id.'/struk')->assertRedirect(route('masuk'));
});

// ---- Buyer history + detail: PAY-49/50, ORD-09 ----

test('PAY-49 buyer payment history lists only own payments, filters by status, and has an empty state', function () {
    $buyer = m3cBuyer();
    $other = m3cBuyer();

    $this->actingAs($buyer, 'web');
    $this->get('/pembayaran')->assertOk()->assertSee('Belum ada pembayaran.');

    $order = m3cCheckout($this, $buyer, [['product' => m3cProduct(), 'qty' => 1]]);
    $paid = $order->payments()->first();
    m3cWebhook($this, $paid, 'paid')->assertOk();

    $order2 = m3cCheckout($this, $buyer, [['product' => m3cProduct(), 'qty' => 1]]);
    $failed = $order2->payments()->first();
    m3cWebhook($this, $failed, 'failed')->assertOk();

    $otherOrder = m3cCheckout($this, $other, [['product' => m3cProduct(), 'qty' => 1]]);
    $this->get('/pesanan')->assertOk();

    $this->actingAs($buyer, 'web');
    $this->get('/pembayaran')->assertOk()
        ->assertSee($order->order_number)
        ->assertSee($order2->order_number)
        ->assertDontSee($otherOrder->order_number);

    $this->get('/pembayaran?status=paid')->assertOk()
        ->assertSee($order->order_number)
        ->assertDontSee($order2->order_number);

    $this->get('/pembayaran?status=bogus')->assertOk()
        ->assertSee($order->order_number)
        ->assertSee($order2->order_number);
});

test('PAY-49 buyer payment history paginates 10 per page', function () {
    $buyer = m3cBuyer();
    $numbers = [];

    for ($i = 0; $i < 11; $i++) {
        $o = Order::factory()->create(['user_id' => $buyer->id]);
        Payment::factory()->create(['order_id' => $o->id, 'user_id' => $buyer->id, 'status' => 'paid']);
        $numbers[] = $o->order_number;
    }

    $this->actingAs($buyer, 'web');
    $this->get('/pembayaran')->assertOk()
        ->assertDontSee($numbers[0])
        ->assertSee($numbers[10]);
    $this->get('/pembayaran?page=2')->assertOk()
        ->assertSee($numbers[0])
        ->assertDontSee($numbers[10]);
});

test('PAY-50 payment detail is owner-only: another buyer gets 404, a guest is redirected', function () {
    $buyer = m3cBuyer();
    $order = m3cCheckout($this, $buyer, [['product' => m3cProduct(), 'qty' => 1]]);
    $payment = $order->payments()->first();

    $this->post('/keluar');

    $this->get('/pembayaran/'.$payment->id)->assertRedirect(route('masuk'));

    $this->actingAs(m3cBuyer(), 'web');
    $this->get('/pembayaran/'.$payment->id)->assertNotFound();

    $this->actingAs($buyer, 'web');
    $this->get('/pembayaran/'.$payment->id)->assertOk()
        ->assertSee($order->order_number)
        ->assertSee('Timeline Transisi Status');
});

test('ORD-09 order detail links to the payment detail', function () {
    $buyer = m3cBuyer();
    $order = m3cCheckout($this, $buyer, [['product' => m3cProduct(), 'qty' => 1]]);
    $payment = $order->payments()->first();

    $this->actingAs($buyer, 'web');
    $this->get('/pesanan/'.$order->id)->assertOk()
        ->assertSee(route('pembayaran.show', $payment), false)
        ->assertSee('Lihat detail pembayaran');
});

// ---- Admin: PAY-51/52/53, guards ----

test('PAY-51 admin payment list filters by status, method and date without origin column', function () {
    $buyer = m3cBuyer();
    $order = m3cCheckout($this, $buyer, [['product' => m3cProduct(), 'qty' => 1]], 'qris');
    $payment = $order->payments()->first();
    m3cWebhook($this, $payment, 'paid')->assertOk();

    $order2 = m3cCheckout($this, $buyer, [['product' => m3cProduct(), 'qty' => 1]], 'va_bri');

    $admin = m3cAdmin();
    $this->actingAs($admin, 'web');
    $this->get('/admin/pembayaran')->assertOk()
        ->assertSee($order->order_number)
        ->assertSee($order2->order_number)
        ->assertDontSee('Asal data');

    $this->get('/admin/pembayaran?status=paid')->assertOk()
        ->assertSee($order->order_number)
        ->assertDontSee($order2->order_number);

    $this->get('/admin/pembayaran?metode=va_bri')->assertOk()
        ->assertSee($order2->order_number)
        ->assertDontSee($order->order_number);

    $today = now()->format('Y-m-d');
    $this->get('/admin/pembayaran?dari='.$today.'&sampai='.$today)->assertOk()
        ->assertSee($order->order_number);

    $this->get('/admin/pembayaran?dari=2030-01-01')->assertOk()
        ->assertDontSee($order->order_number)
        ->assertSee('Belum ada pembayaran.');
});

test('PAY-52 admin payment detail shows the buyer and the timeline, read-only', function () {
    $buyer = m3cBuyer();
    $order = m3cPaidOrder($this, $buyer);
    $payment = $order->payments()->first();

    $admin = m3cAdmin();
    $this->actingAs($admin, 'web');
    $this->get('/admin/pembayaran/'.$payment->id)->assertOk()
        ->assertSee($buyer->name)
        ->assertSee($buyer->email)
        ->assertSee('Timeline Transisi Status')
        ->assertSee('Terbayar')
        ->assertDontSee('Ubah status pembayaran')
        ->assertDontSee('name="status"', false);
});

test('M3c guards: a buyer cannot open admin payment pages, a guest is redirected', function () {
    $this->get('/admin/pembayaran')->assertRedirect(route('masuk', ['aktor' => 'admin_toko']));
    $this->get('/pembayaran')->assertRedirect(route('masuk'));

    $buyer = m3cBuyer();
    $this->actingAs($buyer, 'web');
    $this->get('/admin/pembayaran')->assertForbidden();
    $this->get('/admin/pembayaran/xxx')->assertForbidden();
});

test('PAY-53 no admin route can change a payment', function () {
    $admin = m3cAdmin();
    $buyer = m3cBuyer();
    $order = m3cCheckout($this, $buyer, [['product' => m3cProduct(), 'qty' => 1]]);
    $payment = $order->payments()->first();

    $this->actingAs($admin, 'web');
    $this->post('/admin/pembayaran/'.$payment->id.'/status', ['status' => 'paid'])->assertNotFound();
    $this->post('/admin/pembayaran/'.$payment->id.'/bayar')->assertNotFound();
    expect($this->put('/admin/pembayaran/'.$payment->id, ['status' => 'paid'])->status())->toBeIn([404, 405]);
    expect($this->delete('/admin/pembayaran/'.$payment->id)->status())->toBeIn([404, 405]);
    $this->get('/admin/pembayaran/'.$payment->id.'/ubah')->assertNotFound();

    foreach (Route::getRoutes() as $route) {
        if (str_starts_with($route->uri(), 'admin/pembayaran')) {
            expect(array_intersect(['POST', 'PUT', 'PATCH', 'DELETE'], $route->methods()))->toBeEmpty(
                'Route '.$route->uri().' must not accept a write method (PAY-53).'
            );
        }
    }
});

test('M3c guards: every new protected route declares guard and role', function () {
    $routes = Route::getRoutes();

    foreach ([
        '/pembayaran' => 'role:pembeli',
        '/pembayaran/{payment}' => 'role:pembeli',
        '/pembayaran/{payment}/hasil' => 'role:pembeli',
        '/pembayaran/{payment}/struk' => 'role:pembeli',
        '/admin/pembayaran' => 'role:admin_toko',
        '/admin/pembayaran/{payment}' => 'role:admin_toko',
    ] as $uri => $role) {
        $route = collect($routes)->first(fn ($r) => $r->uri() === ltrim($uri, '/'));
        expect($route)->not->toBeNull('Route '.$uri.' must exist.');
        $middleware = $route->gatherMiddleware();
        expect(collect($middleware)->contains(fn ($m) => str_starts_with((string) $m, 'auth:')))->toBeTrue();
        expect(collect($middleware)->contains(fn ($m) => (string) $m === $role))->toBeTrue();
    }

    $this->assertTrue(true);
});

test('PAY-54 all payment views omit SIMULASI and trace triggers', function () {
    $buyer = m3cBuyer();
    $admin = m3cAdmin();

    $order = m3cCheckout($this, $buyer, [['product' => m3cProduct(), 'qty' => 1]]);
    $paid = $order->payments()->first();

    $this->actingAs($buyer, 'web');
    $this->get('/pesanan/'.$order->id.'/bayar')->assertOk()->assertDontSee('SIMULASI');

    m3cWebhook($this, $paid, 'paid')->assertOk();
    $this->get('/pembayaran/'.$paid->id.'/hasil')->assertOk()->assertDontSee('SIMULASI');
    $this->get('/pembayaran/'.$paid->id.'/struk')->assertOk()->assertDontSee('SIMULASI');
    $this->get('/pembayaran')->assertOk()->assertDontSee('SIMULASI');
    $this->get('/pembayaran/'.$paid->id)->assertOk()
        ->assertDontSee('SIMULASI')
        ->assertDontSee('data-trace-trigger', false)
        ->assertDontSee('trace-popup', false);

    $this->actingAs($admin, 'web');
    $this->get('/admin/pembayaran')->assertOk()->assertDontSee('SIMULASI');
    $this->get('/admin/pembayaran/'.$paid->id)->assertOk()
        ->assertDontSee('SIMULASI')
        ->assertDontSee('data-trace-trigger', false)
        ->assertDontSee('trace-popup', false);

    $order2 = m3cCheckout($this, $buyer, [['product' => m3cProduct(), 'qty' => 1]]);
    $failed = $order2->payments()->first();
    m3cWebhook($this, $failed, 'failed')->assertOk();

    $this->actingAs($buyer, 'web');
    $this->get('/pembayaran/'.$failed->id.'/hasil')->assertOk()->assertDontSee('SIMULASI');

    $order3 = m3cCheckout($this, $buyer, [['product' => m3cProduct(), 'qty' => 1]]);
    $expired = $order3->payments()->first();

    try {
        Carbon::setTestNow(now()->addMinutes(31));
        $this->get('/pembayaran/'.$expired->id.'/hasil')->assertOk()->assertDontSee('SIMULASI');
    } finally {
        Carbon::setTestNow();
    }
});
