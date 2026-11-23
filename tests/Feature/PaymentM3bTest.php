<?php

use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Payments\PaymentAdapter;
use App\Payments\ProviderRegistry;
use App\Payments\SignatureVerifier;
use App\Providers\AppServiceProvider;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

// Arsitektur PaymentAdapter netral ('manual') & webhook endpoint
// (/api/v1/webhooks/payments/manual) setelah penghapusan simulator.

beforeEach(function () {
    config()->set('payment.driver', 'manual');
    config()->set('payment.webhook_secret', 'test-secret');
});

function m3bBuyer(): User
{
    return User::factory()->create();
}

function m3bAdmin(): User
{
    return User::factory()->admin()->create();
}

function m3bProduct(array $attributes = []): Product
{
    return Product::factory()->create(array_merge(
        ['stock_on_hand' => 10, 'stock_reserved' => 0, 'price_idr' => 5000],
        $attributes,
    ));
}

function m3bPickup(): array
{
    return [
        'pickup_date' => Carbon::now('Asia/Makassar')->addDay()->toDateString(),
        'pickup_time' => '10:00',
    ];
}

function m3bCheckout($t, User $buyer, array $lines, string $method = 'qris', ?string $key = null): Order
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
        ...m3bPickup(),
    ])->assertRedirect();

    return Order::where('user_id', $buyer->id)->latest('id')->first();
}

function m3bSign(string $body): string
{
    return SignatureVerifier::sign($body, 'test-secret');
}

function m3bWebhook($t, array $payload, string $mode = 'valid'): TestResponse
{
    $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    $server = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'];

    if ($mode === 'valid') {
        $server['HTTP_X_WEBHOOK_SIGNATURE'] = m3bSign($body);
    } elseif ($mode === 'wrong') {
        $server['HTTP_X_WEBHOOK_SIGNATURE'] = SignatureVerifier::PREFIX.str_repeat('0', 64);
    }

    return $t->call('POST', '/api/v1/webhooks/payments/manual', [], [], [], $server, $body);
}

function m3bPaidPayload(Payment $payment): array
{
    return [
        'payment_id' => $payment->id,
        'order_id' => $payment->order->order_number,
        'status' => 'paid',
        'paid_at' => now()->toIso8601String(),
        'reference' => $payment->reference_code,
    ];
}

function m3bFailedPayload(Payment $payment): array
{
    return [
        'payment_id' => $payment->id,
        'order_id' => $payment->order->order_number,
        'status' => 'failed',
        'reference' => $payment->reference_code,
    ];
}

// ---- Instruction page ----

test('PAY-11 instruction page shows neutral payment reference, total and countdown for its owner', function () {
    $buyer = m3bBuyer();
    $product = m3bProduct(['name' => 'Pulpen Instruksi', 'price_idr' => 7000]);
    $order = m3bCheckout($this, $buyer, [['product' => $product, 'qty' => 2]]);
    $payment = $order->payments()->first();

    $this->actingAs($buyer, 'web');
    $this->get('/pesanan/'.$order->id.'/bayar')->assertOk()
        ->assertSee('Pembayaran')
        ->assertDontSee('SIMULASI')
        ->assertDontSee('/pay/sim/')
        ->assertSee($order->order_number)
        ->assertSee('Rp 14.000')
        ->assertSee('QRIS')
        ->assertSee('Kode referensi pembayaran')
        ->assertSee($payment->reference_code)
        ->assertSee('Salin')
        ->assertSee($payment->expires_at->toIso8601String(), false)
        ->assertDontSee($buyer->name);
});

test('PAY-11 instruction page is owner-only: another buyer gets 404, a guest is redirected', function () {
    $buyer = m3bBuyer();
    $other = m3bBuyer();
    $order = m3bCheckout($this, $buyer, [['product' => m3bProduct(), 'qty' => 1]]);

    $this->actingAs($other, 'web');
    $this->get('/pesanan/'.$order->id.'/bayar')->assertNotFound();

    $this->post('/keluar');
    $this->get('/pesanan/'.$order->id.'/bayar')->assertRedirect('/masuk');
});

test('PAY-09 VA instruction shows the 16-digit VA number grouped and a copy button without simulator captions', function () {
    $buyer = m3bBuyer();
    $order = m3bCheckout($this, $buyer, [['product' => m3bProduct(), 'qty' => 1]], 'va_bri');
    $payment = $order->payments()->first();

    expect($payment->reference_code)->toMatch('/^\d{16}$/');
    expect($payment->reference_code)->toStartWith('99901');

    $this->actingAs($buyer, 'web');
    $this->get('/pesanan/'.$order->id.'/bayar')->assertOk()
        ->assertSee('VA BRI')
        ->assertSee('Nomor Virtual Account')
        ->assertDontSee('Nomor VA — bukan nomor rekening.')
        ->assertDontSee('SIMULASI')
        ->assertSee($payment->formattedReference())
        ->assertSee('Salin');
});

test('PAY-13 status endpoint returns payment/order status and redirects when finalized via webhook', function () {
    $buyer = m3bBuyer();
    $order = m3bCheckout($this, $buyer, [['product' => m3bProduct(), 'qty' => 1]]);
    $payment = $order->payments()->first();

    $this->actingAs($buyer, 'web');
    $this->getJson('/pembayaran/'.$payment->id.'/status')->assertOk()
        ->assertJson(['status' => 'pending', 'order_status' => 'menunggu_pembayaran', 'redirect_url' => null]);

    m3bWebhook($this, m3bPaidPayload($payment))->assertOk();

    $this->getJson('/pembayaran/'.$payment->id.'/status')->assertOk()
        ->assertJson(['status' => 'paid', 'order_status' => 'dibayar'])
        ->assertJsonPath('redirect_url', route('pembayaran.hasil', $payment));

    $this->actingAs(m3bBuyer(), 'web');
    $this->getJson('/pembayaran/'.$payment->id.'/status')->assertNotFound();
});

test('PAY-11 instruction page redirects when the order is no longer waiting or has no pending payment', function () {
    $buyer = m3bBuyer();
    $order = m3bCheckout($this, $buyer, [['product' => m3bProduct(), 'qty' => 1]]);
    $payment = $order->payments()->first();

    m3bWebhook($this, m3bPaidPayload($payment))->assertOk();

    $this->actingAs($buyer, 'web');
    $this->get('/pesanan/'.$order->id.'/bayar')->assertRedirect('/pesanan/'.$order->id);
});

test('ORD-02 checkout success redirects to the payment instruction page', function () {
    $buyer = m3bBuyer();
    $product = m3bProduct();

    $this->actingAs($buyer, 'web');
    $this->post('/keranjang/tambah', ['product_id' => $product->id, 'quantity' => 1])->assertRedirect('/keranjang');

    $response = $this->post('/checkout', [
        'payment_method' => 'qris',
        'checkout_key' => (string) Str::uuid(),
        ...m3bPickup(),
    ]);

    $order = Order::where('user_id', $buyer->id)->latest('id')->first();
    $response->assertRedirect(route('pesanan.bayar', $order));
});

test('Simulator routes and internal simulator API endpoint are removed (404)', function () {
    $buyer = m3bBuyer();
    $order = m3Checkout($this, $buyer, [['product' => m3bProduct(), 'qty' => 1]]);
    $payment = $order->payments()->first();

    $this->get('/pay/sim/'.$payment->payment_token)->assertNotFound();
    $this->post('/pay/sim/'.$payment->payment_token.'/bayar')->assertNotFound();
    $this->post('/pay/sim/'.$payment->payment_token.'/gagal')->assertNotFound();
    $this->postJson('/api/v1/simulator/payments', [
        'order_id' => $order->order_number,
        'amount' => 5000,
    ])->assertNotFound();
});

// ---- Webhook ----

test('PAY-24/25 webhook with a valid signature processes a paid callback and confirms stock', function () {
    $buyer = m3bBuyer();
    $product = m3bProduct(['stock_on_hand' => 10]);
    $order = m3bCheckout($this, $buyer, [['product' => $product, 'qty' => 2]]);
    $payment = $order->payments()->first();

    m3bWebhook($this, m3bPaidPayload($payment))->assertOk()
        ->assertJson(['result' => 'processed', 'payment_status' => 'paid', 'order_status' => 'dibayar']);

    expect($payment->fresh()->status)->toBe('paid');
    expect($order->fresh()->status)->toBe('dibayar');
    expect($product->fresh()->stock_reserved)->toBe(0);
    expect($product->fresh()->stock_on_hand)->toBe(8);
});

test('PAY-25 missing or wrong signature returns 401 without any change', function () {
    $buyer = m3bBuyer();
    $order = m3bCheckout($this, $buyer, [['product' => m3bProduct(), 'qty' => 1]]);
    $payment = $order->payments()->first();

    m3bWebhook($this, m3bPaidPayload($payment), 'missing')->assertStatus(401);
    m3bWebhook($this, m3bPaidPayload($payment), 'wrong')->assertStatus(401);

    expect($payment->fresh()->status)->toBe('pending');
    expect($order->fresh()->status)->toBe('menunggu_pembayaran');
});

test('PAY-26 invalid payload returns 422 without changes', function () {
    $buyer = m3bBuyer();
    $order = m3bCheckout($this, $buyer, [['product' => m3bProduct(), 'qty' => 1]]);
    $payment = $order->payments()->first();

    $base = m3bPaidPayload($payment);

    m3bWebhook($this, array_merge($base, ['status' => 'bogus']))->assertStatus(422);
    m3bWebhook($this, array_diff_key($base, ['reference' => 1]))->assertStatus(422);
    m3bWebhook($this, array_diff_key($base, ['paid_at' => 1]))->assertStatus(422);
    m3bWebhook($this, array_merge($base, ['paid_at' => 'bukan-tanggal']))->assertStatus(422);
    m3bWebhook($this, array_merge($base, ['payment_id' => 'tidak-ada']))->assertStatus(404);

    expect($payment->fresh()->status)->toBe('pending');
    expect($order->fresh()->status)->toBe('menunggu_pembayaran');
});

test('PAY-26 order_id or reference mismatch returns 422', function () {
    $buyer = m3bBuyer();
    $order = m3bCheckout($this, $buyer, [['product' => m3bProduct(), 'qty' => 1]]);
    $payment = $order->payments()->first();

    m3bWebhook($this, array_merge(m3bPaidPayload($payment), ['order_id' => 'JBR-000000-XXXXXX']))->assertStatus(422);
    m3bWebhook($this, array_merge(m3bPaidPayload($payment), ['reference' => 'PAY-XXXXXXXXXX']))->assertStatus(422);

    expect($payment->fresh()->status)->toBe('pending');
});

test('PAY-23 an amount in the payload that differs from the order is rejected', function () {
    $buyer = m3bBuyer();
    $order = m3bCheckout($this, $buyer, [['product' => m3bProduct(), 'qty' => 1]]);
    $payment = $order->payments()->first();

    m3bWebhook($this, array_merge(m3bPaidPayload($payment), ['amount' => 999999]))->assertStatus(422);

    expect($payment->fresh()->status)->toBe('pending');
});

test('PAY-24 unknown or removed provider returns 404', function () {
    $body = json_encode(['status' => 'paid']);
    $server = [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
        'HTTP_X_WEBHOOK_SIGNATURE' => m3bSign($body),
    ];

    $this->call('POST', '/api/v1/webhooks/payments/stripe', [], [], [], $server, $body)->assertNotFound();
    $this->call('POST', '/api/v1/webhooks/payments/simulator', [], [], [], $server, $body)->assertNotFound();
});

test('PAY-27 the same callback twice returns 200 already_processed with no double effects', function () {
    $buyer = m3bBuyer();
    $product = m3bProduct(['stock_on_hand' => 10]);
    $order = m3bCheckout($this, $buyer, [['product' => $product, 'qty' => 1]]);
    $payment = $order->payments()->first();

    m3bWebhook($this, m3bPaidPayload($payment))->assertOk()
        ->assertJson(['result' => 'processed']);
    m3bWebhook($this, m3bPaidPayload($payment))->assertOk()
        ->assertJson(['result' => 'already_processed']);

    expect($payment->fresh()->status)->toBe('paid');
    expect($product->fresh()->stock_on_hand)->toBe(9);
    $this->assertDatabaseCount('payment_status_transitions', 2);
});

test('PAY-27 a different result for a final payment returns 409', function () {
    $buyer = m3bBuyer();
    $order = m3bCheckout($this, $buyer, [['product' => m3bProduct(), 'qty' => 1]]);
    $payment = $order->payments()->first();

    m3bWebhook($this, m3bPaidPayload($payment))->assertOk();
    m3bWebhook($this, m3bFailedPayload($payment))->assertStatus(409);

    expect($payment->fresh()->status)->toBe('paid');
    expect($order->fresh()->status)->toBe('dibayar');
});

test('PAY-27 a paid callback after the deadline is processed as expired', function () {
    $buyer = m3bBuyer();
    $product = m3bProduct(['stock_on_hand' => 10]);
    $order = m3bCheckout($this, $buyer, [['product' => $product, 'qty' => 1]]);
    $payment = $order->payments()->first();

    Carbon::setTestNow(now()->addMinutes(31));

    m3bWebhook($this, m3bPaidPayload($payment))->assertOk()
        ->assertJson(['result' => 'processed', 'payment_status' => 'expired']);

    expect($payment->fresh()->status)->toBe('expired');
    expect($order->fresh()->status)->toBe('kedaluwarsa');
    expect($product->fresh()->stock_reserved)->toBe(0);

    Carbon::setTestNow();
});

// ---- Repay ----

test('PAY-31 after a failed payment the buyer can repay with another method', function () {
    $buyer = m3bBuyer();
    $order = m3bCheckout($this, $buyer, [['product' => m3bProduct(), 'qty' => 1]], 'qris');
    $oldPayment = $order->payments()->first();

    m3bWebhook($this, m3bFailedPayload($oldPayment))->assertOk();
    expect($oldPayment->fresh()->status)->toBe('failed');

    $this->actingAs($buyer, 'web');
    $this->get('/pesanan/'.$order->id)->assertOk()->assertSee('Bayar ulang');

    $this->post('/pesanan/'.$order->id.'/bayar-ulang', ['payment_method' => 'va_bca'])
        ->assertRedirect(route('pesanan.bayar', $order));

    $newPayment = $order->fresh()->payments()->where('status', 'pending')->first();
    expect($newPayment)->not->toBeNull();
    expect($newPayment->payment_method)->toBe('va_bca');
    expect($newPayment->id)->not->toBe($oldPayment->id);
    expect($newPayment->payment_token)->not->toBe($oldPayment->payment_token);
    expect($newPayment->reference_code)->not->toBe($oldPayment->reference_code);
    expect($newPayment->expires_at->equalTo($order->payment_deadline_at))->toBeTrue();
    expect($oldPayment->fresh()->status)->toBe('failed');
    expect($order->fresh()->payments()->where('status', 'pending')->count())->toBe(1);
});

test('PAY-31 repay is rejected while a payment is still pending', function () {
    $buyer = m3bBuyer();
    $order = m3bCheckout($this, $buyer, [['product' => m3bProduct(), 'qty' => 1]]);

    $this->actingAs($buyer, 'web');
    $this->post('/pesanan/'.$order->id.'/bayar-ulang', ['payment_method' => 'gopay'])
        ->assertRedirect(route('pesanan.bayar', $order));

    expect($order->fresh()->payments()->count())->toBe(1);
});

test('PAY-31 repay is rejected after the deadline', function () {
    $buyer = m3bBuyer();
    $order = m3bCheckout($this, $buyer, [['product' => m3bProduct(), 'qty' => 1]]);
    $payment = $order->payments()->first();

    m3bWebhook($this, m3bFailedPayload($payment))->assertOk();

    Carbon::setTestNow(now()->addMinutes(31));

    $this->actingAs($buyer, 'web');
    $this->post('/pesanan/'.$order->id.'/bayar-ulang', ['payment_method' => 'gopay'])
        ->assertRedirect(route('pesanan.show', $order));

    expect($order->fresh()->payments()->count())->toBe(1);
    expect($order->fresh()->status)->toBe('kedaluwarsa');

    Carbon::setTestNow();
});

// ---- Scheduled expiry ----

test('PAY-32/34 the scheduled store command expires overdue orders atomically', function () {
    $buyer = m3bBuyer();
    $product = m3bProduct(['stock_on_hand' => 10]);
    $order1 = m3bCheckout($this, $buyer, [['product' => $product, 'qty' => 1]]);
    $order2 = m3bCheckout($this, $buyer, [['product' => $product, 'qty' => 2]], 'gopay', (string) Str::uuid());

    Carbon::setTestNow(now()->addMinutes(31));

    $this->artisan('store:expire-overdue-orders')->assertExitCode(0);

    foreach ([$order1, $order2] as $order) {
        expect($order->fresh()->status)->toBe('kedaluwarsa');
        expect($order->fresh()->payments()->first()->status)->toBe('expired');
    }
    expect($product->fresh()->stock_reserved)->toBe(0);

    $this->artisan('store:expire-overdue-orders')->assertExitCode(0);

    Carbon::setTestNow();
});

test('PAY-34 an order without a pending payment after failure still expires via the store command', function () {
    $buyer = m3bBuyer();
    $product = m3bProduct(['stock_on_hand' => 10]);
    $order = m3bCheckout($this, $buyer, [['product' => $product, 'qty' => 1]]);
    $payment = $order->payments()->first();

    m3bWebhook($this, m3bFailedPayload($payment))->assertOk();

    Carbon::setTestNow(now()->addMinutes(31));

    $this->artisan('store:expire-overdue-orders')->assertExitCode(0);

    expect($order->fresh()->status)->toBe('kedaluwarsa');
    expect($payment->fresh()->status)->toBe('failed');
    expect($product->fresh()->stock_reserved)->toBe(0);

    Carbon::setTestNow();
});

// ---- Driver / adapter ----

test('PAY-57 an empty, unknown, or removed simulator driver is rejected with a clear message', function () {
    expect(fn () => ProviderRegistry::validateDriver(''))->toThrow(LogicException::class, 'PAYMENT_DRIVER');
    expect(fn () => ProviderRegistry::validateDriver('stripe'))->toThrow(LogicException::class, 'PAYMENT_DRIVER');
    expect(fn () => ProviderRegistry::validateDriver('simulator'))->toThrow(LogicException::class, 'PAYMENT_DRIVER');
    expect(ProviderRegistry::validateDriver('manual'))->toBe('manual');
    expect(ProviderRegistry::isKnownProvider('manual'))->toBeTrue();
    expect(ProviderRegistry::isKnownProvider('simulator'))->toBeFalse();
    expect(ProviderRegistry::isKnownProvider('stripe'))->toBeFalse();
});

test('PAY-57 resolving the adapter with an unknown driver fails and boot validates config', function () {
    config()->set('payment.driver', 'bogus');

    expect(fn () => app(PaymentAdapter::class))->toThrow(LogicException::class, 'PAYMENT_DRIVER');

    $provider = new AppServiceProvider($this->app);
    expect(fn () => $provider->boot())->toThrow(LogicException::class, 'PAYMENT_DRIVER');
});

test('PAY-55 checkout and order code never refer to a concrete adapter class directly', function () {
    foreach ([
        'app/Services/CheckoutService.php',
        'app/Services/OrderExpiryService.php',
        'app/Http/Controllers/Shop/CheckoutController.php',
        'app/Http/Controllers/Shop/OrderController.php',
        'app/Http/Controllers/Shop/PaymentInstructionController.php',
    ] as $path) {
        $source = file_get_contents(base_path($path));
        expect($source)->not->toContain('ManualPaymentAdapter');
        expect($source)->not->toContain('SimulatorPaymentAdapter');
    }
});
