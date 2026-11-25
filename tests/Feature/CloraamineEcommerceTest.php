<?php

use App\Models\Address;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

test('CatalogSeeder populates Cloraamine K-pop merch and custom print categories with conditions and pre-order metadata', function () {
    $this->seed(CatalogSeeder::class);

    expect(Category::pluck('name')->all())->toEqualCanonicalizing([
        'Photocard',
        'Album',
        'Merchandise',
        'Stiker Custom',
        'Pin Button Custom',
    ]);

    expect(Product::where('condition', 'bekas')->count())->toBeGreaterThanOrEqual(2);
    expect(Product::where('is_preorder', true)->count())->toBeGreaterThanOrEqual(2);
    expect(Product::where('is_featured', true)->count())->toBeGreaterThanOrEqual(3);
    expect(Product::where('is_active', false)->count())->toBe(1);
});

test('admin can create and update product with photo upload, condition, and pre-order metadata and storefront filters work', function () {
    Storage::fake('local');

    $admin = User::factory()->admin()->create();
    $category = Category::factory()->create(['name' => 'Photocard Koleksi']);

    $this->actingAs($admin, 'web');
    $photo = UploadedFile::fake()->create('photocard.jpg', 120, 'image/jpeg');

    $this->post('/admin/produk', [
        'name' => 'Photocard Edisi Spesial Holo',
        'category_id' => $category->id,
        'description' => 'Photocard holo resmi.',
        'price_idr' => 35000,
        'stock_on_hand' => 10,
        'condition' => 'baru',
        'is_preorder' => '1',
        'preorder_eta' => 'Estimasi 30 hari',
        'is_featured' => '1',
        'image' => $photo,
        'is_active' => '1',
    ])->assertRedirect('/admin/produk');

    $product = Product::where('name', 'Photocard Edisi Spesial Holo')->firstOrFail();
    expect($product->is_preorder)->toBeTrue();
    expect($product->preorder_eta)->toBe('Estimasi 30 hari');
    expect($product->is_featured)->toBeTrue();
    expect($product->hasImage())->toBeTrue();
    Storage::disk('local')->assertExists($product->image_path);

    // Public can stream active product photo
    $this->get('/media/produk/'.$product->id)->assertOk();

    // Create a secondhand ready-stock item
    $usedProduct = Product::factory()->create([
        'category_id' => $category->id,
        'name' => 'Photocard Preloved Mulus',
        'price_idr' => 12000,
        'condition' => 'bekas',
        'is_preorder' => false,
        'stock_on_hand' => 2,
    ]);

    // Filter by kondisi=bekas
    $this->get('/katalog?kondisi=bekas')
        ->assertOk()
        ->assertSee('Photocard Preloved Mulus')
        ->assertDontSee('Photocard Edisi Spesial Holo');

    // Filter by tipe=preorder
    $this->get('/katalog?tipe=preorder')
        ->assertOk()
        ->assertSee('Photocard Edisi Spesial Holo')
        ->assertDontSee('Photocard Preloved Mulus');
});

test('buyer can checkout with courier delivery using saved address, upload payment proof, and admin can view proof, confirm payment, set tracking number, and export CSV', function () {
    Storage::fake('local');

    $admin = User::factory()->admin()->create();
    $buyer = User::factory()->create(['name' => 'Nadia Kolektor', 'phone' => '081298765432']);
    $otherBuyer = User::factory()->create();

    $address = Address::factory()->create([
        'user_id' => $buyer->id,
        'recipient_name' => 'Nadia Penerima',
        'recipient_phone' => '081298765432',
        'street_address' => 'Jl. Melati No. 12',
        'city' => 'Makassar',
        'province' => 'Sulawesi Selatan',
        'postal_code' => '90111',
    ]);

    $product = Product::factory()->create([
        'name' => 'Album Edisi Reguler Clora',
        'price_idr' => 200000,
        'stock_on_hand' => 5,
    ]);

    $this->actingAs($buyer, 'web');
    $this->post('/keranjang/tambah', ['product_id' => $product->id, 'quantity' => 1])->assertRedirect();

    $this->post('/checkout', [
        'payment_method' => 'qris',
        'checkout_key' => (string) Str::uuid(),
        'fulfillment_method' => 'delivery',
        'courier_code' => 'jne',
        'address_id' => $address->id,
        'buyer_note' => 'Tolong bungkus bubble wrap tebal',
    ])->assertRedirect();

    $order = Order::where('user_id', $buyer->id)->latest('id')->firstOrFail();
    expect($order->isDelivery())->toBeTrue();
    expect($order->courier_name)->toBe('JNE REG');
    expect($order->subtotal_idr)->toBe(200000);
    expect($order->shipping_cost_idr)->toBe(12000);
    expect($order->total_idr)->toBe(212000);
    expect($order->shipping_recipient_name)->toBe('Nadia Penerima');
    expect($order->shipping_address_snapshot)->toContain('Jl. Melati No. 12');
    expect($order->buyer_note)->toBe('Tolong bungkus bubble wrap tebal');

    $payment = $order->pendingPayment();
    expect($payment->amount_idr)->toBe(212000);

    // Upload payment proof
    $proofFile = UploadedFile::fake()->create('bukti-bayar.png', 150, 'image/png');
    $this->post('/pembayaran/'.$payment->id.'/bukti', [
        'proof' => $proofFile,
    ])->assertRedirect('/pesanan/'.$order->id.'/bayar');

    $payment->refresh();
    expect($payment->hasProof())->toBeTrue();
    Storage::disk('local')->assertExists($payment->proof_path);

    // Owning buyer can view proof; other buyer gets 404
    $this->get('/pembayaran/'.$payment->id.'/bukti')->assertOk();
    $this->actingAs($otherBuyer, 'web')->get('/pembayaran/'.$payment->id.'/bukti')->assertNotFound();

    // Admin can view proof and confirm payment directly
    $this->actingAs($admin, 'web');
    $this->get('/admin/pembayaran/'.$payment->id.'/bukti')->assertOk();

    $this->post('/admin/pesanan/'.$order->id.'/konfirmasi-bayar')->assertRedirect('/admin/pesanan/'.$order->id);

    $order->refresh();
    $payment->refresh();
    expect($payment->status)->toBe('paid');
    expect($order->status)->toBe('dibayar');

    // Admin updates delivery status to diproses ('Sedang Dikirim') with tracking number
    $this->post('/admin/pesanan/'.$order->id.'/status', [
        'to_status' => 'diproses',
        'tracking_number' => 'JNE123456789ID',
    ])->assertRedirect('/admin/pesanan/'.$order->id);

    $order->refresh();
    expect($order->status)->toBe('diproses');
    expect($order->statusLabel())->toBe('Sedang Dikirim');
    expect($order->tracking_number)->toBe('JNE123456789ID');

    // Admin CSV export includes order number, courier, and tracking number
    $csvResponse = $this->get('/admin/pesanan/ekspor-csv');
    $csvResponse->assertOk();
    expect($csvResponse->streamedContent())
        ->toContain($order->order_number)
        ->toContain('JNE REG')
        ->toContain('JNE123456789ID');
});
