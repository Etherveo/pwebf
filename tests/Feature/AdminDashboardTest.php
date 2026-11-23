<?php

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Services\PaymentStateMachine;
use Carbon\Carbon;
use Illuminate\Support\Str;

test('admin dashboard summarizes 30-day SQL sales, pending queues, top products, and recent orders', function () {
    $admin = User::factory()->admin()->create();
    $buyer = User::factory()->create(['name' => 'Citra Pembeli']);
    $pTop = Product::factory()->create(['name' => 'Produk Laris Dash', 'price_idr' => 10000, 'stock_on_hand' => 20]);
    $pOther = Product::factory()->create(['name' => 'Produk Kedua Dash', 'price_idr' => 15000, 'stock_on_hand' => 20]);

    // Pesanan terbayar baru (masuk ringkasan 30 hari)
    $this->actingAs($buyer, 'web');
    $this->post('/keranjang/tambah', ['product_id' => $pTop->id, 'quantity' => 3])->assertRedirect();
    $this->post('/keranjang/tambah', ['product_id' => $pOther->id, 'quantity' => 1])->assertRedirect();
    $this->post('/checkout', [
        'payment_method' => 'qris',
        'checkout_key' => (string) Str::uuid(),
        'pickup_date' => Carbon::now('Asia/Makassar')->addDay()->toDateString(),
        'pickup_time' => '10:00',
    ])->assertRedirect();

    $paidOrder = Order::where('user_id', $buyer->id)->latest('id')->first();
    app(PaymentStateMachine::class)->markPaid($paidOrder->payments()->first());

    // Pesanan lama (> 40 hari lalu): tidak boleh ikut agregat 30 hari
    $oldOrder = Order::factory()->paid()->create([
        'user_id' => $buyer->id,
        'total_idr' => 999000,
        'paid_at' => now()->subDays(45),
        'created_at' => now()->subDays(45),
    ]);
    OrderItem::factory()->create([
        'order_id' => $oldOrder->id,
        'product_id' => $pOther->id,
        'product_name_snapshot' => $pOther->name,
        'unit_price_idr' => 999000,
        'quantity' => 1,
        'subtotal_idr' => 999000,
    ]);

    $this->actingAs($admin, 'web');
    $this->get('/admin')->assertOk()
        ->assertSee('Dashboard Toko')
        ->assertSee('Rp 45.000')
        ->assertDontSee('Rp 1.044.000')
        ->assertSee('Produk Laris Dash')
        ->assertSee($paidOrder->order_number)
        ->assertSee('Citra Pembeli');
});

test('admin dashboard blocks buyers and removed python analytics routes return 404', function () {
    $this->get('/admin')->assertRedirect('/masuk?aktor=admin_toko');

    $buyer = User::factory()->create();
    $this->actingAs($buyer, 'web');
    $this->get('/admin')->assertForbidden();

    $admin = User::factory()->admin()->create();
    $this->actingAs($admin, 'web');
    foreach ([
        '/admin/tren',
        '/admin/funnel',
        '/admin/perbandingan',
        '/admin/aktivitas',
        '/admin/aktivitas/riwayat',
        '/admin/kategori-analisis',
    ] as $legacyUri) {
        $this->get($legacyUri)->assertNotFound();
    }
});
