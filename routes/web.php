<?php

use App\Http\Controllers\Admin\BuyerAccountController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\PaymentController as AdminPaymentController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\StoreInsightsController;
use App\Http\Controllers\Auth\BuyerAuthController;
use App\Http\Controllers\Shop\CartController;
use App\Http\Controllers\Shop\CatalogController;
use App\Http\Controllers\Shop\CheckoutController;
use App\Http\Controllers\Shop\HomeController;
use App\Http\Controllers\Shop\OrderController as ShopOrderController;
use App\Http\Controllers\Shop\PaymentController as ShopPaymentController;
use App\Http\Controllers\Shop\PaymentInstructionController;
use App\Http\Controllers\Shop\ProfileController;
use Illuminate\Support\Facades\Route;

// Public storefront & home
Route::get('/', [HomeController::class, 'index'])->name('home');

// Buyer registration & unified login (pembeli | admin_toko)
Route::get('/daftar', [BuyerAuthController::class, 'showRegister'])->name('daftar');
Route::post('/daftar', [BuyerAuthController::class, 'register']);

Route::get('/masuk', [BuyerAuthController::class, 'showLogin'])->name('masuk');
Route::post('/masuk', [BuyerAuthController::class, 'login']);
Route::post('/keluar', [BuyerAuthController::class, 'logout'])
    ->middleware('auth:web')->name('keluar');

Route::get('/admin/masuk', function () {
    return redirect()->route('masuk', ['aktor' => 'admin_toko']);
})->name('admin.masuk');

// Public catalog
Route::get('/katalog', [CatalogController::class, 'index'])->name('katalog');
Route::get('/katalog/cari', [CatalogController::class, 'index'])->name('katalog.cari');
Route::get('/produk/{produk}', [CatalogController::class, 'show'])->name('produk.show');
Route::get('/media/produk/{produk}', [CatalogController::class, 'photo'])->name('produk.foto');

// Buyer authenticated surface
Route::middleware(['auth:web', 'active', 'role:pembeli', 'no-cache'])->group(function () {
    Route::get('/keranjang', [CartController::class, 'index'])->name('keranjang.index');
    Route::post('/keranjang/tambah', [CartController::class, 'store'])->name('keranjang.tambah');
    Route::put('/keranjang/{item}', [CartController::class, 'update'])->name('keranjang.update');
    Route::delete('/keranjang/{item}', [CartController::class, 'destroy'])->name('keranjang.destroy');

    Route::get('/checkout', [CheckoutController::class, 'show'])->name('checkout.show');
    Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');

    Route::get('/pesanan', [ShopOrderController::class, 'index'])->name('pesanan.index');
    Route::get('/pesanan/{order}', [ShopOrderController::class, 'show'])->name('pesanan.show');
    Route::post('/pesanan/{order}/batal', [ShopOrderController::class, 'cancel'])->name('pesanan.batal');

    Route::get('/pesanan/{order}/bayar', [PaymentInstructionController::class, 'show'])->name('pesanan.bayar');
    Route::post('/pesanan/{order}/bayar-ulang', [PaymentInstructionController::class, 'repay'])->name('pesanan.bayar-ulang');
    Route::get('/pembayaran/{payment}/status', [PaymentInstructionController::class, 'status'])->name('pembayaran.status');
    Route::post('/pembayaran/{payment}/bukti', [PaymentInstructionController::class, 'uploadProof'])->name('pembayaran.bukti.store');
    Route::get('/pembayaran/{payment}/bukti', [PaymentInstructionController::class, 'viewProof'])->name('pembayaran.bukti.show');

    Route::get('/pembayaran', [ShopPaymentController::class, 'index'])->name('pembayaran.index');
    Route::get('/pembayaran/{payment}', [ShopPaymentController::class, 'show'])->name('pembayaran.show');
    Route::get('/pembayaran/{payment}/hasil', [ShopPaymentController::class, 'result'])->name('pembayaran.hasil');
    Route::get('/pembayaran/{payment}/struk', [ShopPaymentController::class, 'receipt'])->name('pembayaran.struk');

    Route::get('/profil', [ProfileController::class, 'show'])->name('profil.show');
    Route::get('/profil/ubah', [ProfileController::class, 'edit'])->name('profil.edit');
    Route::put('/profil', [ProfileController::class, 'update'])->name('profil.update');
    Route::put('/profil/alamat', [ProfileController::class, 'updateAddress'])->name('profil.alamat.update');
});

// Admin Toko surface
Route::middleware(['auth:web', 'active', 'role:admin_toko', 'no-cache'])->group(function () {
    Route::get('/admin', [DashboardController::class, 'index'])->name('admin.dashboard');
    Route::get('/admin/funnel', [StoreInsightsController::class, 'funnel'])->name('admin.funnel');
    Route::get('/admin/aktivitas', [StoreInsightsController::class, 'activity'])->name('admin.aktivitas');
    Route::get('/admin/aktivitas/riwayat', [StoreInsightsController::class, 'history'])->name('admin.aktivitas.riwayat');

    Route::get('/admin/kategori', [CategoryController::class, 'index'])->name('admin.kategori.index');
    Route::get('/admin/kategori/tambah', [CategoryController::class, 'create'])->name('admin.kategori.create');
    Route::post('/admin/kategori', [CategoryController::class, 'store'])->name('admin.kategori.store');
    Route::get('/admin/kategori/{kategori}/ubah', [CategoryController::class, 'edit'])->name('admin.kategori.edit');
    Route::put('/admin/kategori/{kategori}', [CategoryController::class, 'update'])->name('admin.kategori.update');
    Route::delete('/admin/kategori/{kategori}', [CategoryController::class, 'destroy'])->name('admin.kategori.destroy');

    Route::get('/admin/produk', [ProductController::class, 'index'])->name('admin.produk.index');
    Route::get('/admin/produk/tambah', [ProductController::class, 'create'])->name('admin.produk.create');
    Route::post('/admin/produk', [ProductController::class, 'store'])->name('admin.produk.store');
    Route::get('/admin/produk/{produk}/ubah', [ProductController::class, 'edit'])->name('admin.produk.edit');
    Route::put('/admin/produk/{produk}', [ProductController::class, 'update'])->name('admin.produk.update');
    Route::post('/admin/produk/{produk}/toggle', [ProductController::class, 'toggle'])->name('admin.produk.toggle');
    Route::get('/admin/produk/{produk}/stok', [ProductController::class, 'stockForm'])->name('admin.produk.stok');
    Route::post('/admin/produk/{produk}/stok', [ProductController::class, 'adjustStock'])->name('admin.produk.stok.update');
    Route::get('/admin/produk/{produk}/riwayat-stok', [ProductController::class, 'history'])->name('admin.produk.riwayat');

    Route::get('/admin/akun-pembeli', [BuyerAccountController::class, 'index'])->name('admin.akun-pembeli.index');
    Route::get('/admin/akun-pembeli/{account}', [BuyerAccountController::class, 'show'])->name('admin.akun-pembeli.show');
    Route::post('/admin/akun-pembeli/{account}/status', [BuyerAccountController::class, 'updateStatus'])->name('admin.akun-pembeli.status');

    Route::get('/admin/pesanan', [AdminOrderController::class, 'index'])->name('admin.pesanan.index');
    Route::get('/admin/pesanan/ekspor-csv', [AdminOrderController::class, 'exportCsv'])->name('admin.pesanan.csv');
    Route::get('/admin/pesanan/{order}', [AdminOrderController::class, 'show'])->name('admin.pesanan.show');
    Route::post('/admin/pesanan/{order}/status', [AdminOrderController::class, 'updateStatus'])->name('admin.pesanan.status');
    Route::post('/admin/pesanan/{order}/konfirmasi-bayar', [AdminOrderController::class, 'confirmPayment'])->name('admin.pesanan.konfirmasi-bayar');

    Route::get('/admin/pembayaran', [AdminPaymentController::class, 'index'])->name('admin.pembayaran.index');
    Route::get('/admin/pembayaran/{payment}', [AdminPaymentController::class, 'show'])->name('admin.pembayaran.show');
    Route::get('/admin/pembayaran/{payment}/bukti', [AdminPaymentController::class, 'viewProof'])->name('admin.pembayaran.bukti.show');
});
