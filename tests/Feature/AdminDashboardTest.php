<?php

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Services\PaymentStateMachine;
use Carbon\Carbon;
use Illuminate\Support\Str;

test('admin dashboard summarizes SQL sales, categories, chart config, pending queues, top products, and recent orders', function () {
    $admin = User::factory()->admin()->create();
    $buyer = User::factory()->create(['name' => 'Citra Pembeli']);
    $category = Category::factory()->create(['name' => 'Alat Tulis Uji']);
    $pTop = Product::factory()->create([
        'category_id' => $category->id,
        'name' => 'Produk Laris Dash',
        'price_idr' => 10000,
        'stock_on_hand' => 20,
    ]);
    $pOther = Product::factory()->create([
        'category_id' => $category->id,
        'name' => 'Produk Kedua Dash',
        'price_idr' => 15000,
        'stock_on_hand' => 20,
    ]);

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

    // Pesanan lama (> 40 hari lalu): tidak boleh ikut agregat 30 hari default
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
        ->assertSee('Grafik Penjualan Harian')
        ->assertSee('Ringkasan Kategori')
        ->assertSee('Alat Tulis Uji')
        ->assertSee('data-chart', false)
        ->assertSee('Rp 45.000')
        ->assertDontSee('Rp 1.044.000')
        ->assertSee('Produk Laris Dash')
        ->assertSee($paidOrder->order_number)
        ->assertSee('Citra Pembeli');
});

test('admin insight routes enforce admin_toko role and permanently removed python routes return 404', function () {
    foreach (['/admin', '/admin/funnel', '/admin/aktivitas', '/admin/aktivitas/riwayat'] as $guestProtected) {
        $this->get($guestProtected)->assertRedirect('/masuk?aktor=admin_toko');
    }

    $buyer = User::factory()->create();
    $this->actingAs($buyer, 'web');
    foreach (['/admin', '/admin/funnel', '/admin/aktivitas', '/admin/aktivitas/riwayat'] as $buyerBlocked) {
        $this->get($buyerBlocked)->assertForbidden();
    }

    $admin = User::factory()->admin()->create();
    $this->actingAs($admin, 'web');
    foreach ([
        '/admin/tren',
        '/admin/perbandingan',
        '/admin/kategori-analisis',
    ] as $removedUri) {
        $this->get($removedUri)->assertNotFound();
    }

    foreach (['/admin/funnel', '/admin/aktivitas', '/admin/aktivitas/riwayat'] as $restoredUri) {
        $this->get($restoredUri)->assertOk();
    }
});
