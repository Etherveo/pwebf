<?php

use App\Models\Category;
use App\Models\Event;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\CheckoutService;
use App\Services\OrderExpiryService;
use App\Services\PaymentStateMachine;
use Carbon\Carbon;
use Illuminate\Support\Str;

test('native event counters record buyer views (with 30m dedup), cart adds, bookings, purchases, and cancels', function () {
    $admin = User::factory()->admin()->create();
    $buyer = User::factory()->create(['name' => 'Rina Pembeli']);
    $otherBuyer = User::factory()->create(['name' => 'Tono Pembeli']);
    $product = Product::factory()->create(['price_idr' => 20000, 'stock_on_hand' => 15]);
    $secondProduct = Product::factory()->create(['price_idr' => 15000, 'stock_on_hand' => 15]);

    // Tamu dan admin tidak dicatat sebagai view produk
    $this->get('/produk/'.$product->id)->assertOk();
    $this->actingAs($admin, 'web')->get('/produk/'.$product->id)->assertOk();
    expect(Event::where('event_type', 'view')->count())->toBe(0);

    // Pembeli melihat produk -> tercatat 1x, refresh dalam 30 menit terdeduplikasi
    $this->actingAs($buyer, 'web');
    $this->get('/produk/'.$product->id)->assertOk();
    $this->get('/produk/'.$product->id)->assertOk();
    expect(Event::where('event_type', 'view')->where('product_id', $product->id)->count())->toBe(1);

    // Setelah > 30 menit, view kedua tercatat
    $this->travel(31)->minutes();
    $this->get('/produk/'.$product->id)->assertOk();
    expect(Event::where('event_type', 'view')->where('product_id', $product->id)->count())->toBe(2);

    // Add to cart gagal (qty invalid / stok kurang) tidak menulis event
    $this->post('/keranjang/tambah', ['product_id' => $product->id, 'quantity' => 50])
        ->assertSessionHasErrors('quantity');
    expect(Event::where('event_type', 'add_to_cart')->count())->toBe(0);

    // Add to cart berhasil -> tercatat
    $this->post('/keranjang/tambah', ['product_id' => $product->id, 'quantity' => 2])->assertRedirect();
    expect(Event::where('event_type', 'add_to_cart')->where('product_id', $product->id)->count())->toBe(1);

    // Checkout -> tercatat booking
    $this->post('/checkout', [
        'payment_method' => 'qris',
        'checkout_key' => (string) Str::uuid(),
        'pickup_date' => Carbon::now('Asia/Makassar')->addDay()->toDateString(),
        'pickup_time' => '10:00',
    ])->assertRedirect();
    $paidOrder = Order::where('user_id', $buyer->id)->latest('id')->first();
    expect(Event::where('event_type', 'booking')->where('order_id', $paidOrder->id)->count())->toBe(1);

    // Bayar -> tercatat purchase
    app(PaymentStateMachine::class)->markPaid($paidOrder->payments()->first());
    expect(Event::where('event_type', 'purchase')->where('order_id', $paidOrder->id)->count())->toBe(1);

    // Pembatalan oleh pembeli -> cancel reason buyer
    $this->post('/keranjang/tambah', ['product_id' => $secondProduct->id, 'quantity' => 1])->assertRedirect();
    $this->post('/checkout', [
        'payment_method' => 'qris',
        'checkout_key' => (string) Str::uuid(),
        'pickup_date' => Carbon::now('Asia/Makassar')->addDay()->toDateString(),
        'pickup_time' => '11:00',
    ])->assertRedirect();
    $cancelledOrder = Order::where('user_id', $buyer->id)->latest('id')->first();
    app(CheckoutService::class)->cancelOrder($buyer, $cancelledOrder);
    expect(Event::where('event_type', 'cancel')->where('order_id', $cancelledOrder->id)->where('reason', 'buyer')->count())->toBe(1);

    // Kedaluwarsa oleh sistem -> cancel reason expired
    $this->actingAs($otherBuyer, 'web');
    $this->post('/keranjang/tambah', ['product_id' => $secondProduct->id, 'quantity' => 1])->assertRedirect();
    $this->post('/checkout', [
        'payment_method' => 'qris',
        'checkout_key' => (string) Str::uuid(),
        'pickup_date' => Carbon::now('Asia/Makassar')->addDay()->toDateString(),
        'pickup_time' => '12:00',
    ])->assertRedirect();
    $expiredOrder = Order::where('user_id', $otherBuyer->id)->latest('id')->first();
    $expiredOrder->update(['payment_deadline_at' => now()->subMinute()]);
    app(OrderExpiryService::class)->expireIfOverdue($expiredOrder);
    expect(Event::where('event_type', 'cancel')->where('order_id', $expiredOrder->id)->where('reason', 'expired')->count())->toBe(1);
});

test('dashboard period presets, chart metric switchers, and recent order pagination work cleanly', function () {
    $admin = User::factory()->admin()->create();
    $buyer = User::factory()->create();
    Order::factory()->count(7)->paid()->create(['user_id' => $buyer->id]);

    $this->actingAs($admin, 'web');

    $this->get('/admin?rentang=today&grafik=unit')
        ->assertOk()
        ->assertSee('Hari Ini')
        ->assertSee('Unit Terjual')
        ->assertDontSee('data-chart-rupiah', false);

    $this->get('/admin?rentang=all&grafik=pesanan')
        ->assertOk()
        ->assertSee('Semua Waktu')
        ->assertSee('Pesanan Terbayar');

    // Tanggal invalid tidak meledak (jatuh kembali aman ke default)
    $this->get('/admin?dari=bukan-tanggal&sampai=2026-99-99')
        ->assertOk()
        ->assertSee('Dashboard Toko');

    // Pagination daftar pesanan terbaru pada halaman 2
    $this->get('/admin?page=2')
        ->assertOk()
        ->assertSee('Pesanan Terbaru');
});

test('funnel and activity pages support category/sort pill filters, charts, and pagination while preserving query params', function () {
    $admin = User::factory()->admin()->create();
    $buyer = User::factory()->create(['name' => 'Sari Aktivitas', 'email' => 'rahasia-sari@example.test']);
    $catA = Category::factory()->create(['name' => 'Alat Gambar']);
    $catB = Category::factory()->create(['name' => 'Buku Catatan']);

    $productsA = Product::factory()->count(12)->sequence(
        fn ($seq) => ['name' => sprintf('Produk Gambar %02d', $seq->index + 1), 'category_id' => $catA->id]
    )->create();
    $productB = Product::factory()->create(['name' => 'Buku Khusus', 'category_id' => $catB->id]);

    // Produk Gambar 01 punya 3 view + 1 cart + 1 purchase; Produk lain 1 view agar ada 12 produk aktif
    foreach ($productsA as $idx => $prod) {
        $views = $idx === 0 ? 3 : 1;
        for ($i = 0; $i < $views; $i++) {
            Event::create([
                'event_type' => 'view',
                'user_id' => $buyer->id,
                'product_id' => $prod->id,
                'occurred_at' => now()->subHours($i + 1),
            ]);
        }
    }
    Event::create([
        'event_type' => 'add_to_cart',
        'user_id' => $buyer->id,
        'product_id' => $productsA[0]->id,
        'occurred_at' => now()->subMinutes(20),
    ]);
    Event::create([
        'event_type' => 'view',
        'user_id' => $buyer->id,
        'product_id' => $productB->id,
        'occurred_at' => now()->subMinutes(10),
    ]);

    $this->actingAs($admin, 'web');

    // Funnel filter kategori + per_page=10 + pagination ke page 2 menjaga query params
    $funnelPage1 = $this->get('/admin/funnel?kategori='.$catA->id.'&urut=views_desc&aktif=1&per_page=10');
    $funnelPage1->assertOk()
        ->assertSee('Funnel Pembelian')
        ->assertSee('Produk Gambar 01')
        ->assertDontSee('Buku Khusus')
        ->assertSee('page=2', false)
        ->assertSee('per_page=10', false);

    $this->get('/admin/funnel?kategori='.$catA->id.'&urut=views_desc&aktif=1&per_page=10&page=2')
        ->assertOk()
        ->assertSee('Produk Gambar');

    // Aktivitas produk: filter kategori, sort pil, pagination
    $activityPage1 = $this->get('/admin/aktivitas?kategori='.$catA->id.'&urut=views_desc&per_page=10');
    $activityPage1->assertOk()
        ->assertSee('Grafik Aktivitas Harian')
        ->assertSee('data-chart', false)
        ->assertSee('Produk Gambar 01')
        ->assertDontSee('Buku Khusus')
        ->assertSee('page=2', false);

    $this->get('/admin/aktivitas?kategori='.$catA->id.'&urut=views_desc&per_page=10&page=2')
        ->assertOk();
});

test('activity history paginates events, filters by event type & WITA dates, and never leaks buyer email', function () {
    $admin = User::factory()->admin()->create();
    $buyer = User::factory()->create(['name' => 'Budi Riwayat', 'email' => 'budi-privasi@example.test']);
    $product = Product::factory()->create(['name' => 'Spidol Uji Riwayat']);

    // 18 events agar page=2 dengan per_page=15 terisi
    for ($i = 0; $i < 16; $i++) {
        Event::create([
            'event_type' => 'view',
            'user_id' => $buyer->id,
            'product_id' => $product->id,
            'occurred_at' => Carbon::create(2026, 11, 20, 10, 0, 0, 'Asia/Makassar')->subMinutes($i)->utc(),
        ]);
    }

    // Event add_to_cart pada 2026-11-21 00:30 WITA (= 2026-11-20 16:30 UTC)
    Event::create([
        'event_type' => 'add_to_cart',
        'user_id' => $buyer->id,
        'product_id' => $product->id,
        'occurred_at' => Carbon::create(2026, 11, 21, 0, 30, 0, 'Asia/Makassar')->utc(),
    ]);

    $this->actingAs($admin, 'web');

    // Filter tanggal WITA 2026-11-21 + jenis add_to_cart hanya menampilkan event setelah tengah malam WITA
    $this->get('/admin/aktivitas/riwayat?jenis=add_to_cart&dari=2026-11-21&sampai=2026-11-21')
        ->assertOk()
        ->assertSee('Budi Riwayat')
        ->assertSee('Spidol Uji Riwayat')
        ->assertSee('Masuk Keranjang')
        ->assertDontSee('budi-privasi@example.test');

    // Pagination page=1 -> page=2 untuk filter view + per_page=15
    $this->get('/admin/aktivitas/riwayat?jenis=view&rentang=all&per_page=15')
        ->assertOk()
        ->assertSee('page=2', false)
        ->assertDontSee('budi-privasi@example.test');

    $this->get('/admin/aktivitas/riwayat?jenis=view&rentang=all&per_page=15&page=2')
        ->assertOk()
        ->assertSee('Budi Riwayat')
        ->assertDontSee('budi-privasi@example.test');
});
