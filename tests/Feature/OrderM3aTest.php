<?php

use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\PaymentStatusTransition;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\OrderExpiryService;
use App\Services\OrderStateMachine;
use App\Services\PaymentStateMachine;
use Carbon\Carbon;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

// Cart, stock, checkout, order cancellation, expiry, and state machine flows.

function m3Buyer(): User
{
    return User::factory()->create();
}

function m3Admin(): User
{
    return User::factory()->admin()->create();
}

function m3Product(array $attributes = []): Product
{
    return Product::factory()->create(array_merge(
        ['stock_on_hand' => 10, 'stock_reserved' => 0, 'price_idr' => 5000],
        $attributes,
    ));
}

// Build a cart through the real HTTP routes, then check out. Returns
// the created order (fresh).
// Pickup schedule (keputusan Ren 2026-11-09): checkout wajib membawa
// jadwal — besok pukul 10:00 WITA, selalu valid.
function m3Pickup(): array
{
    return [
        'pickup_date' => Carbon::now('Asia/Makassar')->addDay()->toDateString(),
        'pickup_time' => '10:00',
    ];
}

function m3Checkout($t, User $buyer, array $lines, string $method = 'qris', ?string $key = null): Order
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
        ...m3Pickup(),
    ])->assertRedirect();

    return Order::where('user_id', $buyer->id)->latest('id')->first();
}

function m3PaidOrder($t, User $buyer, array $lines): Order
{
    $order = m3Checkout($t, $buyer, $lines);
    $payment = $order->payments()->first();
    app(PaymentStateMachine::class)->markPaid($payment);

    return $order->fresh();
}

// ---- CRT ----

test('CRT-01 adding a product to the cart stores the item cleanly', function () {
    $buyer = m3Buyer();
    $product = m3Product(['name' => 'Pulpen CRT', 'price_idr' => 5000, 'stock_on_hand' => 10]);

    $this->actingAs($buyer, 'web');
    $this->post('/keranjang/tambah', ['product_id' => $product->id, 'quantity' => 2])
        ->assertRedirect('/keranjang');

    $this->assertDatabaseHas('cart_items', ['quantity' => 2]);
    $this->get('/keranjang')->assertOk()->assertSee('Pulpen CRT')->assertSee('Rp 10.000');
});

test('CRT-01 adding the same product merges quantities, capped at 99 and at available stock', function () {
    $buyer = m3Buyer();
    $product = m3Product(['stock_on_hand' => 10]);

    $this->actingAs($buyer, 'web');
    $this->post('/keranjang/tambah', ['product_id' => $product->id, 'quantity' => 2])->assertRedirect('/keranjang');
    $this->post('/keranjang/tambah', ['product_id' => $product->id, 'quantity' => 3])->assertRedirect('/keranjang');

    $this->assertDatabaseCount('cart_items', 1);
    $this->assertDatabaseHas('cart_items', ['quantity' => 5]);

    // Merged total above available stock is rejected, old quantity kept.
    $this->post('/keranjang/tambah', ['product_id' => $product->id, 'quantity' => 6])
        ->assertSessionHasErrors('quantity');
    $this->assertDatabaseHas('cart_items', ['quantity' => 5]);
});

test('CRT-01 adding fails cleanly: inactive product, quantity above 99, quantity above stock', function () {
    $buyer = m3Buyer();
    $inactive = m3Product(['is_active' => false]);
    $scarce = m3Product(['stock_on_hand' => 3]);

    $this->actingAs($buyer, 'web');

    $this->post('/keranjang/tambah', ['product_id' => $inactive->id, 'quantity' => 1])
        ->assertSessionHasErrors('product_id');
    $this->post('/keranjang/tambah', ['product_id' => $scarce->id, 'quantity' => 100])
        ->assertSessionHasErrors('quantity');
    $this->post('/keranjang/tambah', ['product_id' => $scarce->id, 'quantity' => 4])
        ->assertSessionHasErrors('quantity');

    $this->assertDatabaseCount('cart_items', 0);
});

test('CRT-02 changing quantity works and out-of-range keeps the old quantity', function () {
    $buyer = m3Buyer();
    $product = m3Product(['stock_on_hand' => 3]);

    $this->actingAs($buyer, 'web');
    $this->post('/keranjang/tambah', ['product_id' => $product->id, 'quantity' => 2])->assertRedirect('/keranjang');
    $itemId = CartItem::first()->id;

    // Valid change: 2 -> 3.
    $this->put('/keranjang/'.$itemId, ['quantity' => 3])->assertRedirect('/keranjang');
    $this->assertDatabaseHas('cart_items', ['id' => $itemId, 'quantity' => 3]);

    // Above available stock: rejected, quantity stays 3.
    $this->put('/keranjang/'.$itemId, ['quantity' => 4])->assertSessionHasErrors('quantity');
    $this->assertDatabaseHas('cart_items', ['id' => $itemId, 'quantity' => 3]);

    // Above 99: rejected by the form request, quantity stays 3.
    $this->put('/keranjang/'.$itemId, ['quantity' => 100])->assertSessionHasErrors('quantity');
    $this->assertDatabaseHas('cart_items', ['id' => $itemId, 'quantity' => 3]);
});

test('CRT-03 removing an item deletes it cleanly', function () {
    $buyer = m3Buyer();
    $product = m3Product();

    $this->actingAs($buyer, 'web');
    $this->post('/keranjang/tambah', ['product_id' => $product->id, 'quantity' => 1])->assertRedirect('/keranjang');
    $itemId = CartItem::first()->id;

    $this->delete('/keranjang/'.$itemId)->assertRedirect('/keranjang');
    $this->assertDatabaseCount('cart_items', 0);
    $this->get('/keranjang')->assertOk()->assertSee('Keranjang masih kosong.');
});

test('CRT-04 cart page shows items, current unit prices, subtotals, server total and the header count', function () {
    $buyer = m3Buyer();
    $a = m3Product(['name' => 'Pulpen A', 'price_idr' => 5000, 'stock_on_hand' => 10]);
    $b = m3Product(['name' => 'Buku B', 'price_idr' => 2500, 'stock_on_hand' => 10]);

    $this->actingAs($buyer, 'web');
    $this->post('/keranjang/tambah', ['product_id' => $a->id, 'quantity' => 2])->assertRedirect('/keranjang');
    $this->post('/keranjang/tambah', ['product_id' => $b->id, 'quantity' => 1])->assertRedirect('/keranjang');

    $this->get('/keranjang')->assertOk()
        ->assertSee('Pulpen A')->assertSee('Buku B')
        ->assertSee('Rp 5.000')->assertSee('Rp 2.500')
        ->assertSee('Rp 10.000')->assertSee('Rp 12.500')
        ->assertSee('Keranjang (3)');
});

test('CRT-04 empty cart shows an empty state with an action to the catalog', function () {
    $this->actingAs(m3Buyer(), 'web');
    $this->get('/keranjang')->assertOk()
        ->assertSee('Keranjang masih kosong.')
        ->assertSee('Lihat katalog');
});

test('CRT-05 a blocked item is flagged on the cart page and blocks checkout until fixed or removed', function () {
    $buyer = m3Buyer();
    $product = m3Product(['name' => 'Pulpen Blokir', 'stock_on_hand' => 2]);

    $this->actingAs($buyer, 'web');
    $this->post('/keranjang/tambah', ['product_id' => $product->id, 'quantity' => 2])->assertRedirect('/keranjang');

    // Stock drops below the cart quantity after the add.
    Product::query()->whereKey($product->id)->update(['stock_on_hand' => 1]);

    $this->get('/keranjang')->assertOk()
        ->assertSee('Perhatian:')->assertSee('Stok tersedia (1) kurang dari jumlah di keranjang (2).')
        ->assertDontSee('Lanjut ke Checkout');
    $this->get('/checkout')->assertRedirect('/keranjang');
    $this->post('/checkout', ['payment_method' => 'qris', 'checkout_key' => (string) Str::uuid(), ...m3Pickup()])
        ->assertSessionHasErrors('keranjang');
    $this->assertDatabaseCount('orders', 0);

    // Removing the item unblocks checkout (cart is empty, so the
    // checkout page itself redirects back to the cart).
    $itemId = CartItem::first()->id;
    $this->delete('/keranjang/'.$itemId)->assertRedirect('/keranjang');
    $this->get('/keranjang')->assertOk()->assertDontSee('Perhatian:');
});

test('CRT-05 an inactive product in the cart blocks checkout', function () {
    $buyer = m3Buyer();
    $product = m3Product(['name' => 'Pulpen Mati']);

    $this->actingAs($buyer, 'web');
    $this->post('/keranjang/tambah', ['product_id' => $product->id, 'quantity' => 1])->assertRedirect('/keranjang');
    Product::query()->whereKey($product->id)->update(['is_active' => false]);

    $this->get('/keranjang')->assertOk()->assertSee('Produk sudah tidak aktif.');
    $this->post('/checkout', ['payment_method' => 'qris', 'checkout_key' => (string) Str::uuid(), ...m3Pickup()])
        ->assertSessionHasErrors('keranjang');
    $this->assertDatabaseCount('orders', 0);
});

test('CRT-06 cart persists per account and another account cannot read or change it (ACC-09)', function () {
    $buyer = m3Buyer();
    $other = m3Buyer();
    $product = m3Product();

    $this->actingAs($buyer, 'web');
    $this->post('/keranjang/tambah', ['product_id' => $product->id, 'quantity' => 2])->assertRedirect('/keranjang');
    $itemId = CartItem::first()->id;

    // The other account sees an empty cart and cannot touch the item.
    $this->actingAs($other, 'web');
    $this->get('/keranjang')->assertOk()->assertSee('Keranjang masih kosong.');
    $this->put('/keranjang/'.$itemId, ['quantity' => 1])->assertNotFound();
    $this->delete('/keranjang/'.$itemId)->assertNotFound();
    $this->assertDatabaseHas('cart_items', ['id' => $itemId, 'quantity' => 2]);

    // The owner's cart is unchanged after the other account's attempts.
    $this->actingAs($buyer, 'web');
    $this->get('/keranjang')->assertOk()->assertSee('Keranjang (2)');
});

// ---- ORD / STK: checkout ----

test('ORD-02 checkout is atomic: order, snapshot items, reserved stock, empty cart, pending payment', function () {
    $buyer = m3Buyer();
    $a = m3Product(['name' => 'Pulpen Checkout', 'price_idr' => 5000, 'stock_on_hand' => 10]);
    $b = m3Product(['name' => 'Buku Checkout', 'price_idr' => 2500, 'stock_on_hand' => 10]);

    $order = m3Checkout($this, $buyer, [
        ['product' => $a, 'qty' => 2],
        ['product' => $b, 'qty' => 1],
    ]);

    expect($order->status)->toBe('menunggu_pembayaran');
    expect($order->total_idr)->toBe(12500);
    expect($order->order_number)->toMatch('/^CL-\d{6}-[A-HJ-NP-Z2-9]{6}$/');
    expect((int) $order->created_at->diffInMinutes($order->payment_deadline_at))->toBe(30);

    $items = $order->items()->orderBy('product_id')->get();
    expect($items)->toHaveCount(2);
    expect($items[0]->product_name_snapshot)->toBe('Pulpen Checkout');
    expect($items[0]->unit_price_idr)->toBe(5000);
    expect($items[0]->subtotal_idr)->toBe(10000);
    expect($items[1]->subtotal_idr)->toBe(2500);

    expect($a->fresh()->stock_on_hand)->toBe(10);
    expect($a->fresh()->stock_reserved)->toBe(2);
    expect($a->fresh()->stock_available)->toBe(8);
    expect($b->fresh()->stock_reserved)->toBe(1);
    $this->assertDatabaseHas('stock_movements', [
        'product_id' => $a->id, 'type' => 'cadangkan', 'delta_reserved' => 2, 'order_id' => $order->id,
    ]);

    // Cart is empty after checkout.
    $this->assertDatabaseCount('cart_items', 0);

    // One pending payment, amount = server total, expires at deadline, neutral provider.
    $payment = $order->payments()->first();
    expect($payment)->not->toBeNull();
    expect($payment->status)->toBe('pending');
    expect($payment->amount_idr)->toBe(12500);
    expect($payment->payment_method)->toBe('qris');
    expect($payment->provider)->toBe('manual');
    expect($payment->qr_url)->toBeNull();
    expect($payment->expires_at->equalTo($order->payment_deadline_at))->toBeTrue();
    expect($payment->reference_code)->toStartWith('PAY-');
    expect(strlen($payment->payment_token))->toBe(43);

    // Initial history row: null -> menunggu_pembayaran, trigger pembeli.
    expect(OrderStatusHistory::where('order_id', $order->id)->count())->toBe(1);
    $this->assertDatabaseHas('order_status_histories', [
        'order_id' => $order->id, 'from_status' => null,
        'to_status' => 'menunggu_pembayaran', 'trigger' => 'pembeli',
        'actor_type' => 'pembeli', 'actor_id' => $buyer->id,
    ]);
    $this->assertDatabaseHas('payment_status_transitions', [
        'payment_id' => $payment->id, 'from_status' => null,
        'to_status' => 'pending', 'trigger' => 'checkout',
    ]);
});

test('ORD-01 checkout summary shows items, server total and payment methods', function () {
    $buyer = m3Buyer();
    $product = m3Product(['name' => 'Pulpen Ringkas', 'price_idr' => 7000]);

    $this->actingAs($buyer, 'web');
    $this->post('/keranjang/tambah', ['product_id' => $product->id, 'quantity' => 2])->assertRedirect('/keranjang');

    $this->get('/checkout')->assertOk()
        ->assertSee('Pulpen Ringkas')->assertSee('Rp 14.000')
        ->assertSee('QRIS')->assertSee('Dana')
        ->assertSee('GoPay')->assertSee('VA BRI')
        ->assertSee('VA BCA')->assertSee('VA BNI')
        ->assertDontSee('SIMULASI')
        ->assertSee('checkout_key', false);

    // An unknown method is rejected by the server.
    $this->post('/checkout', ['payment_method' => 'kartu_kredit', 'checkout_key' => (string) Str::uuid(), ...m3Pickup()])
        ->assertSessionHasErrors('payment_method');
    $this->assertDatabaseCount('orders', 0);
});

test('ORD-02 checkout with a VA method creates a 16-digit dummy VA reference', function () {
    $buyer = m3Buyer();
    $order = m3Checkout($this, $buyer, [
        ['product' => m3Product(), 'qty' => 1],
    ], 'va_bri');

    $payment = $order->payments()->first();
    expect($payment->payment_method)->toBe('va_bri');
    expect($payment->reference_code)->toMatch('/^99901\d{11}$/');
});

test('ORD-02 checkout failure rolls everything back: no order, no reservation, cart kept', function () {
    $buyer = m3Buyer();
    $a = m3Product(['name' => 'Pulpen Aman', 'stock_on_hand' => 5]);
    $b = m3Product(['name' => 'Buku Gagal', 'stock_on_hand' => 5]);

    $this->actingAs($buyer, 'web');
    $this->post('/keranjang/tambah', ['product_id' => $a->id, 'quantity' => 2])->assertRedirect('/keranjang');
    $this->post('/keranjang/tambah', ['product_id' => $b->id, 'quantity' => 1])->assertRedirect('/keranjang');

    // Stock of B drops to zero after the add, so checkout must fail.
    Product::query()->whereKey($b->id)->update(['stock_on_hand' => 0]);

    $this->post('/checkout', ['payment_method' => 'qris', 'checkout_key' => (string) Str::uuid(), ...m3Pickup()])
        ->assertSessionHasErrors('keranjang');

    $this->assertDatabaseCount('orders', 0);
    $this->assertDatabaseCount('order_items', 0);
    $this->assertDatabaseCount('payments', 0);
    expect($a->fresh()->stock_reserved)->toBe(0);
    expect(StockMovement::where('type', 'cadangkan')->count())->toBe(0);
    $this->assertDatabaseCount('cart_items', 2);
});

test('ORD-02 re-posting the same checkout_key returns the same order instead of a second one', function () {
    $buyer = m3Buyer();
    $product = m3Product();
    $key = (string) Str::uuid();

    $this->actingAs($buyer, 'web');
    $this->post('/keranjang/tambah', ['product_id' => $product->id, 'quantity' => 1])->assertRedirect('/keranjang');

    $first = $this->post('/checkout', ['payment_method' => 'qris', 'checkout_key' => $key, ...m3Pickup()]);
    $orderId = Order::first()->id;
    // Sejak M3b checkout sukses redirect ke halaman instruksi
    // pembayaran, bukan detail pesanan (perubahan ini terlewat saat
    // M3b memperbarui tes ORD-02 satunya; idempotensi sendiri benar).
    $first->assertRedirect('/pesanan/'.$orderId.'/bayar');

    // The cart is empty now, but the same key still returns the order.
    $second = $this->post('/checkout', ['payment_method' => 'qris', 'checkout_key' => $key, ...m3Pickup()]);
    $second->assertRedirect('/pesanan/'.$orderId.'/bayar');
    $this->assertDatabaseCount('orders', 1);
    $this->assertDatabaseCount('payments', 1);
});

test('ORD-04 a product price change after checkout does not change the old order', function () {
    $buyer = m3Buyer();
    $product = m3Product(['price_idr' => 5000]);

    $order = m3Checkout($this, $buyer, [['product' => $product, 'qty' => 2]]);

    Product::query()->whereKey($product->id)->update(['price_idr' => 9999]);

    $order->refresh();
    expect($order->total_idr)->toBe(10000);
    expect($order->items()->first()->unit_price_idr)->toBe(5000);
    expect($order->items()->first()->product_name_snapshot)->toBe($product->name);
});

// ---- ORD-07 cancel ----

test('ORD-07 buyer cancels their own waiting order: stock released, payment cancelled', function () {
    $buyer = m3Buyer();
    $product = m3Product(['stock_on_hand' => 10]);
    $order = m3Checkout($this, $buyer, [['product' => $product, 'qty' => 3]]);

    expect($product->fresh()->stock_reserved)->toBe(3);

    $this->actingAs($buyer, 'web');
    $this->post('/pesanan/'.$order->id.'/batal')->assertRedirect('/pesanan/'.$order->id);

    $order->refresh();
    expect($order->status)->toBe('dibatalkan');
    expect($order->payments()->first()->status)->toBe('cancelled');
    expect($product->fresh()->stock_reserved)->toBe(0);
    expect($product->fresh()->stock_available)->toBe(10);
    $this->assertDatabaseHas('stock_movements', [
        'product_id' => $product->id, 'type' => 'lepas', 'delta_reserved' => -3, 'order_id' => $order->id,
    ]);
    $this->assertDatabaseHas('order_status_histories', [
        'order_id' => $order->id, 'from_status' => 'menunggu_pembayaran',
        'to_status' => 'dibatalkan', 'trigger' => 'pembeli', 'reason' => 'buyer',
    ]);
    $this->assertDatabaseHas('payment_status_transitions', [
        'payment_id' => $order->payments()->first()->id,
        'from_status' => 'pending', 'to_status' => 'cancelled', 'trigger' => 'pembeli_batal',
    ]);
});

test('ORD-07 a paid or final order cannot be cancelled, and another buyer cannot cancel it', function () {
    $buyer = m3Buyer();
    $other = m3Buyer();
    $paid = m3PaidOrder($this, $buyer, [['product' => m3Product(), 'qty' => 1]]);

    $this->actingAs($buyer, 'web');
    $this->post('/pesanan/'.$paid->id.'/batal')->assertSessionHasErrors('status');
    expect($paid->fresh()->status)->toBe('dibayar');

    $waiting = m3Checkout($this, $buyer, [['product' => m3Product(), 'qty' => 1]]);
    $this->actingAs($other, 'web');
    $this->post('/pesanan/'.$waiting->id.'/batal')->assertNotFound();
    expect($waiting->fresh()->status)->toBe('menunggu_pembayaran');
});

// ---- Expiry (on-access) ----

test('ORD-05/PAY-32 opening an overdue order expires it on access: stock released once only', function () {
    $buyer = m3Buyer();
    $product = m3Product(['stock_on_hand' => 10]);
    $order = m3Checkout($this, $buyer, [['product' => $product, 'qty' => 2]]);

    Carbon::setTestNow(now()->addMinutes(31));

    try {
        $this->actingAs($buyer, 'web');
        $this->get('/pesanan/'.$order->id)->assertOk()->assertSee('Kedaluwarsa');

        $order->refresh();
        expect($order->status)->toBe('kedaluwarsa');
        expect($order->payments()->first()->status)->toBe('expired');
        expect($product->fresh()->stock_reserved)->toBe(0);
        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id, 'to_status' => 'kedaluwarsa',
            'trigger' => 'job_kedaluwarsa', 'reason' => 'expired',
        ]);

        // Second visit: still kedaluwarsa, stock released once only.
        $this->get('/pesanan/'.$order->id)->assertOk();
        expect(StockMovement::where('type', 'lepas')->count())->toBe(1);
    } finally {
        Carbon::setTestNow();
    }
});

test('ORD-05 an order inside its deadline is not expired on access', function () {
    $buyer = m3Buyer();
    $order = m3Checkout($this, $buyer, [['product' => m3Product(), 'qty' => 1]]);

    Carbon::setTestNow(now()->addMinutes(29));

    try {
        $this->actingAs($buyer, 'web');
        $this->get('/pesanan/'.$order->id)->assertOk()->assertSee('Menunggu Pembayaran');
        expect($order->fresh()->status)->toBe('menunggu_pembayaran');
    } finally {
        Carbon::setTestNow();
    }
});

test('OrderExpiryService expires overdue orders in batch and skips the rest', function () {
    $buyer = m3Buyer();
    $overdue = m3Checkout($this, $buyer, [['product' => m3Product(), 'qty' => 1]]);
    $fresh = m3Checkout($this, $buyer, [['product' => m3Product(), 'qty' => 1]]);

    // Only the first order is pushed past its deadline.
    Order::query()->whereKey($overdue->id)->update([
        'payment_deadline_at' => now()->subMinute(),
    ]);

    $expired = app(OrderExpiryService::class)->expireOverdue();

    expect($expired)->toBe(1);
    expect($overdue->fresh()->status)->toBe('kedaluwarsa');
    expect($fresh->fresh()->status)->toBe('menunggu_pembayaran');
});

// ---- State machines ----

test('ORD-06 order state machine accepts only the PRD 9.2 transitions and records history for each', function () {
    $machine = app(OrderStateMachine::class);
    $order = Order::factory()->create(['status' => 'menunggu_pembayaran']);

    // Invalid: menunggu_pembayaran -> selesai / diproses.
    expect(fn () => $machine->transition($order, 'selesai', 'admin', 'admin_toko', 1))
        ->toThrow(ValidationException::class);
    expect(fn () => $machine->transition($order, 'diproses', 'admin', 'admin_toko', 1))
        ->toThrow(ValidationException::class);
    expect($order->fresh()->status)->toBe('menunggu_pembayaran');
    expect(OrderStatusHistory::where('order_id', $order->id)->count())->toBe(0);

    // Valid chain: menunggu -> dibayar -> diproses -> selesai.
    $machine->transition($order, 'dibayar', 'callback_gateway', 'sistem', null);
    expect($order->fresh()->status)->toBe('dibayar');
    expect($order->fresh()->paid_at)->not->toBeNull();
    $machine->transition($order, 'diproses', 'admin', 'admin_toko', 1);
    $machine->transition($order, 'selesai', 'admin', 'admin_toko', 1);
    expect($order->fresh()->status)->toBe('selesai');

    // Final: nothing leaves selesai.
    expect(fn () => $machine->transition($order, 'dibayar', 'admin', 'admin_toko', 1))
        ->toThrow(ValidationException::class);
    expect(OrderStatusHistory::where('order_id', $order->id)->count())->toBe(3);
});

test('PAY-28 payment state machine accepts only pending transitions and final statuses never change', function () {
    $machine = app(PaymentStateMachine::class);
    $payment = Payment::factory()->create(['status' => 'pending']);

    expect(fn () => $machine->transition($payment, 'pending', 'checkout'))
        ->toThrow(ValidationException::class);

    $machine->transition($payment, 'failed', 'callback_gateway');
    expect($payment->fresh()->status)->toBe('failed');

    expect(fn () => $machine->transition($payment, 'paid', 'callback_gateway'))
        ->toThrow(ValidationException::class);
    expect(fn () => $machine->transition($payment, 'cancelled', 'pembeli_batal'))
        ->toThrow(ValidationException::class);
    expect($payment->fresh()->status)->toBe('failed');
    expect(PaymentStatusTransition::where('payment_id', $payment->id)->count())->toBe(1);
});

// ---- Paid path (service only) ----

test('STK-03 markPaid confirms stock once and a second call changes nothing', function () {
    $buyer = m3Buyer();
    $product = m3Product(['stock_on_hand' => 10]);
    $order = m3Checkout($this, $buyer, [['product' => $product, 'qty' => 2]]);
    $payment = $order->payments()->first();

    app(PaymentStateMachine::class)->markPaid($payment);

    $order->refresh();
    expect($order->status)->toBe('dibayar');
    expect($order->paid_at)->not->toBeNull();
    expect($payment->fresh()->status)->toBe('paid');
    expect($payment->fresh()->paid_at)->not->toBeNull();
    expect($product->fresh()->stock_on_hand)->toBe(8);
    expect($product->fresh()->stock_reserved)->toBe(0);
    $this->assertDatabaseHas('stock_movements', [
        'product_id' => $product->id, 'type' => 'konfirmasi',
        'delta_on_hand' => -2, 'delta_reserved' => -2, 'order_id' => $order->id,
    ]);

    // Second paid call: rejected, no double stock effect.
    expect(fn () => app(PaymentStateMachine::class)->markPaid($payment->fresh()))
        ->toThrow(ValidationException::class);
    expect($product->fresh()->stock_on_hand)->toBe(8);
    expect(StockMovement::where('type', 'konfirmasi')->count())->toBe(1);
});

// ---- ORD-08/09 buyer history ----

test('ORD-08 buyer order list is newest first, paginated, filterable, with an empty state', function () {
    $buyer = m3Buyer();
    $first = m3Checkout($this, $buyer, [['product' => m3Product(['name' => 'Produk Pertama']), 'qty' => 1]]);
    $second = m3Checkout($this, $buyer, [['product' => m3Product(['name' => 'Produk Kedua']), 'qty' => 1]]);

    $this->actingAs($buyer, 'web');
    $this->get('/pesanan')->assertOk()
        ->assertSeeInOrder([$second->order_number, $first->order_number])
        ->assertSee($second->formattedTotal());

    // Cancelling the first order, then filtering, shows only it.
    $this->post('/pesanan/'.$first->id.'/batal')->assertRedirect();
    $this->get('/pesanan?status=dibatalkan')->assertOk()
        ->assertSee($first->order_number)->assertDontSee($second->order_number);
    $this->get('/pesanan?status=dibayar')->assertOk()
        ->assertDontSee($first->order_number)->assertDontSee($second->order_number);

    // Another buyer sees nothing of these orders.
    $this->actingAs(m3Buyer(), 'web');
    $this->get('/pesanan')->assertOk()->assertSee('Belum ada pesanan.');
});

test('ORD-09 buyer order detail shows items, total, payment summary, timeline and the real pay link (M3b)', function () {
    $buyer = m3Buyer();
    $product = m3Product(['name' => 'Pulpen Detail Pesanan', 'price_idr' => 8000]);
    $order = m3Checkout($this, $buyer, [['product' => $product, 'qty' => 2]]);

    $this->actingAs($buyer, 'web');
    $this->get('/pesanan/'.$order->id)->assertOk()
        ->assertSee($order->order_number)
        ->assertSee('Pulpen Detail Pesanan')->assertSee('Rp 8.000')->assertSee('Rp 16.000')
        ->assertSee('Menunggu Pembayaran')->assertSee('QRIS')
        ->assertSee('Lanjutkan Pembayaran')->assertSee('Batalkan Pesanan')
        ->assertDontSee('SIMULASI')
        ->assertSee('menunggu_pembayaran');
});

// ---- ORD-10..13 admin ----

test('ORD-12 admin fulfillment moves dibayar to diproses to selesai, and nothing else', function () {
    $buyer = m3Buyer();
    $admin = m3Admin();
    $order = m3PaidOrder($this, $buyer, [['product' => m3Product(), 'qty' => 1]]);

    $this->actingAs($admin, 'web');
    $this->post('/admin/pesanan/'.$order->id.'/status', ['to_status' => 'diproses'])
        ->assertRedirect('/admin/pesanan/'.$order->id);
    expect($order->fresh()->status)->toBe('diproses');
    // M5: picked_up_at stays NULL until the order is selesai.
    expect($order->fresh()->picked_up_at)->toBeNull();
    $this->get('/admin/pesanan/'.$order->id)->assertOk()
        ->assertSee('Tandai Sudah Diambil')
        ->assertDontSee('Tandai Sudah Diambil &amp; Dibayar')
        ->assertDontSee('Tandai Sudah Diambil & Dibayar');
    $this->assertDatabaseHas('order_status_histories', [
        'order_id' => $order->id, 'from_status' => 'dibayar',
        'to_status' => 'diproses', 'trigger' => 'admin',
        'actor_type' => 'admin_toko', 'actor_id' => $admin->id,
    ]);

    $this->post('/admin/pesanan/'.$order->id.'/status', ['to_status' => 'selesai'])
        ->assertRedirect('/admin/pesanan/'.$order->id);
    expect($order->fresh()->status)->toBe('selesai');
    // M5: the pickup-and-pay marker — picked_up_at is stamped on selesai.
    expect($order->fresh()->picked_up_at)->not->toBeNull();
    $this->get('/admin/pesanan/'.$order->id)->assertOk()
        ->assertSee('Selesai')->assertSee('Diambil');
});

test('ORD-12 admin cannot mark an order paid, cancel a paid order, or touch a waiting order', function () {
    $buyer = m3Buyer();
    $admin = m3Admin();
    $waiting = m3Checkout($this, $buyer, [['product' => m3Product(), 'qty' => 1]]);
    $paid = m3PaidOrder($this, $buyer, [['product' => m3Product(), 'qty' => 1]]);

    $this->actingAs($admin, 'web');

    // to_status=dibayar is not even an accepted input value.
    $this->post('/admin/pesanan/'.$waiting->id.'/status', ['to_status' => 'dibayar'])
        ->assertSessionHasErrors('to_status');
    expect($waiting->fresh()->status)->toBe('menunggu_pembayaran');

    // diproses is a valid input but an invalid transition from menunggu.
    $this->post('/admin/pesanan/'.$waiting->id.'/status', ['to_status' => 'diproses'])
        ->assertSessionHasErrors('status');
    expect($waiting->fresh()->status)->toBe('menunggu_pembayaran');

    // No admin cancel route exists at all (ORD-13).
    $this->post('/admin/pesanan/'.$paid->id.'/batal')->assertNotFound();
    expect($paid->fresh()->status)->toBe('dibayar');
});

test('ORD-10/11/13 admin order list filters by status, date range and order number, and detail is read-only', function () {
    $buyer = m3Buyer();
    $admin = m3Admin();
    $cancelled = m3Checkout($this, $buyer, [['product' => m3Product(['name' => 'Produk Batal']), 'qty' => 1]]);
    $paid = m3PaidOrder($this, $buyer, [['product' => m3Product(['name' => 'Produk Bayar']), 'qty' => 1]]);

    $this->actingAs($buyer, 'web');
    $this->post('/pesanan/'.$cancelled->id.'/batal')->assertRedirect();

    $today = now()->format('Y-m-d');
    $this->actingAs($admin, 'web');

    $this->get('/admin/pesanan')->assertOk()
        ->assertSee($cancelled->order_number)->assertSee($paid->order_number)
        ->assertSee($buyer->name);
    $this->get('/admin/pesanan?status=dibatalkan')->assertOk()
        ->assertSee($cancelled->order_number)->assertDontSee($paid->order_number);
    $this->get('/admin/pesanan?q='.$paid->order_number)->assertOk()
        ->assertSee($paid->order_number)->assertDontSee($cancelled->order_number);
    $this->get('/admin/pesanan?dari='.$today.'&sampai='.$today)->assertOk()
        ->assertSee($cancelled->order_number)->assertSee($paid->order_number);
    $this->get('/admin/pesanan?dari=2030-01-01')->assertOk()
        ->assertDontSee($cancelled->order_number);

    $this->get('/admin/pesanan/'.$cancelled->id)->assertOk()
        ->assertSee($cancelled->order_number)->assertSee('Dibatalkan')
        ->assertSee('Produk Batal')->assertSee($buyer->email)
        ->assertSee('alasan: buyer')
        ->assertSee('Tidak ada aksi pemenuhan');
    $this->get('/admin/pesanan/'.$paid->id)->assertOk()
        ->assertSee('Tandai Siap Diambil');
});

// ---- Guards ----

test('M3a guards: guests are redirected, wrong-role users get 403, other buyers orders are 404', function () {
    $buyer = m3Buyer();
    $other = m3Buyer();
    $admin = m3Admin();
    $order = m3Checkout($this, $buyer, [['product' => m3Product(), 'qty' => 1]]);

    $this->post('/keluar');

    $this->get('/keranjang')->assertRedirect('/masuk');
    $this->get('/checkout')->assertRedirect('/masuk');
    $this->get('/pesanan')->assertRedirect('/masuk');
    $this->get('/pesanan/'.$order->id)->assertRedirect('/masuk');
    $this->get('/admin/pesanan')->assertRedirect('/masuk?aktor=admin_toko');

    $this->actingAs($admin, 'web');
    $this->get('/keranjang')->assertForbidden();
    $this->get('/checkout')->assertForbidden();
    $this->get('/pesanan')->assertForbidden();

    $this->actingAs($buyer, 'web');
    $this->get('/admin/pesanan')->assertForbidden();
    $this->get('/admin/pesanan/'.$order->id)->assertForbidden();

    $this->actingAs($other, 'web');
    $this->get('/pesanan/'.$order->id)->assertNotFound();
});

test('M3a guards: every new protected route declares guard and role', function () {
    $routes = Route::getRoutes();

    foreach ([
        '/keranjang' => 'role:pembeli',
        '/keranjang/tambah' => 'role:pembeli',
        '/checkout' => 'role:pembeli',
        '/pesanan' => 'role:pembeli',
        '/pesanan/{order}' => 'role:pembeli',
        '/admin/pesanan' => 'role:admin_toko',
        '/admin/pesanan/{order}' => 'role:admin_toko',
    ] as $uri => $role) {
        $route = collect($routes)->first(fn ($r) => $r->uri() === ltrim($uri, '/'));
        expect($route)->not->toBeNull('Route '.$uri.' must exist.');
        $middleware = $route->gatherMiddleware();
        expect(collect($middleware)->contains(fn ($m) => str_starts_with((string) $m, 'auth:')))->toBeTrue();
        expect(collect($middleware)->contains(fn ($m) => (string) $m === $role))->toBeTrue();
    }

    $this->assertTrue(true);
});
