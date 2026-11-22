# Audit Codebase Baseline: `Etherveo/iraweb`

Dokumen ini disusun dari pemeriksaan langsung seluruh isi source code, konfigurasi, migrasi, view, rute, dan dokumentasi hasil clone `https://github.com/Etherveo/iraweb` (commit HEAD `c66ea94`).  
Status saat audit: **belum ada satu baris pun source code yang diubah**; repo baru saja di-clone (total **354 file** di luar `.git/`, belum ada `vendor/`, `node_modules/`, `.env`, atau file database SQLite).

---

## 1. Ringkasan Eksekutif Arsitektur `iraweb`

`iraweb` adalah aplikasi Laravel (`laravel/framework ^13.17`, `php ^8.3`) dengan pola *modular monolith* yang awalnya dibangun sebagai studi kasus **Ira Stationery** (toko alat tulis di Gorontalo, zona waktu pelaporan WITA / `Asia/Makassar`, penyimpanan UTC) untuk menguji fitur **Trend Analytics (FR-01–FR-06)** menggunakan **data eksperimental/simulator**.

Aplikasi saat ini terdiri dari **empat subsistem yang saling terhubung**:

1. **Storefront & Transaksi Pembeli (`Shop/*`, guard `web`, role `pembeli`)**  
   Katalog publik (`/` dan `/katalog`), detail produk (`/produk/{produk}`), keranjang server-side (`/keranjang`), checkout dengan jadwal pengambilan di toko (`pickup_at`), riwayat pesanan (`/pesanan`), pembatalan pesanan belum bayar, pembayaran (`/pesanan/{order}/bayar`, `/pembayaran/*`), serta halaman profil & alamat (`/profil`).
2. **Back-Office Admin Toko (`Admin/*`, guard `web`, role `admin_toko`)**  
   Manajemen kategori, produk, dan stok beserta buku besar riwayat stok (`stock_movements`), daftar & pemenuhan status pesanan (`dibayar → diproses → selesai` mengisi `picked_up_at`), daftar & detail pembayaran (read-only), manajemen status aktif akun pembeli, dan 7 halaman analitik (`/admin`, `/admin/tren`, `/admin/funnel`, `/admin/perbandingan`, `/admin/aktivitas`, `/admin/aktivitas/riwayat`, `/admin/kategori-analisis`).
3. **Panel Developer & Mesin Simulator Data (`Developer/*`, `app/Simulation/*`, `python/biodata/*`, guard `developer`, role `dev`)**  
   Panel terpisah (`/developer/*`) dengan tabel `developers` tersendiri untuk membuat akun pembeli simulasi massal (maks 100/request lewat `python/biodata/generate_biodata.py`), membuat transaksi backdate (*user-first* dan *period-first*), membuat event funnel sintetis, mengelola run (`simulation_runs`), melihat audit (`simulation_audit_logs`), dan me-reset data simulasi. Juga memiliki CRUD katalog duplikat yang membuka field `release_date`.
4. **Payment Gateway Simulator & Servis Analitik Python**  
   - **Payment Simulator (`app/Payments/*`, `SimulationPageController`, `SimulatorGatewayController`, `InternalDispatcher`, `TraceRecorder`):** mensimulasikan 6 metode pembayaran (`qris`, `dana`, `gopay`, `va_bri`, `va_bca`, `va_bni`) tanpa koneksi gateway riil; QR mengarah ke `/pay/sim/{token}` dengan tombol *Bayar Sekarang* dan *Gagalkan Pembayaran* yang menembak `POST /api/v1/webhooks/payments/simulator` lewat sub-request HTTP kernel internal (`InternalDispatcher`).
   - **Servis Analitik Python (`python/analytics/server.py`):** server HTTP stdlib internal di `127.0.0.1:8001` dengan autentikasi header `X-Internal-Key`. `AnalyticsFactsBuilder` mengagregasi fakta harian dari database, lalu `AnalyticsClient` mengirim JSON ke 8 endpoint `/v1/*` (`summary`, `trend`, `funnel`, `compare`, `interest`, `category`, `category-compare`, `insight`).

---

## 2. Inventaris Teknis & Ketergantungan

### 2.1 Dependensi Runtime
- **Composer (`composer.json`):** `php ^8.3`, `laravel/framework ^13.17`, `laravel/tinker ^3.0`. Dev: `pestphp/pest ^5.3`, `pestphp/pest-plugin-laravel ^5.0`, `laravel/pint ^1.27`, `fakerphp/faker ^1.23`, `mockery/mockery ^1.6`.
- **NPM (`package.json`):** `tailwindcss ^4.0.0`, `@tailwindcss/vite ^4.0.0`, `vite ^8.0.0`, `laravel-vite-plugin ^3.1`, `chart.js ^4.6.1`, `qrcode ^1.5.4`.  
  *Catatan temuan:* `Alpine.js` disebut di `docs/UI.md`, tetapi **tidak terpasang** di `package.json`. Seluruh interaksi frontend saat ini ditulis dengan JavaScript vanilla di `resources/js/app.js` (669 baris).
- **Database (`config/database.php`, `.env.example`, `app/Console/Commands/CheckConfigCommand.php`):**  
  Saat ini dikunci ke **SQLite**. Migrasi `2026_11_05_000004` hingga `2026_11_07_000016` memakai raw `DB::statement("CREATE TABLE ... AUTOINCREMENT ... CHECK (...)")` serta indeks parsial unik (`WHERE status = 'pending'`) yang spesifik SQLite.

### 2.2 Ketergantungan Lintas Modul yang Wajib Diperhatikan Saat Refactoring
Saat menghapus role `developer` dan seluruh subsistem simulasi (Tujuan #3), ditemukan beberapa **ketergantungan tersembunyi** di dalam kode non-developer:

1. **`App\Services\AnalyticsFactsBuilder` bergantung pada `App\Simulation`:**  
   `AnalyticsFactsBuilder` (`app/Services/AnalyticsFactsBuilder.php`) mengimpor `App\Simulation\PeriodResolver` dan `App\Simulation\PeriodRange` untuk konversi tanggal kalender WITA ke rentang UTC.  
   -> **Implikasi:** Jika `app/Simulation/` dihapus dan fitur analitik (atau filter tanggal WITA) masih dipakai, `PeriodResolver` dan `PeriodRange` harus dipindahkan ke `app/Support/` terlebih dahulu (dan melepaskan referensi ke `config('simulation.max_period_days')`).
2. **`App\Services\StockService` & `App\Services\CatalogService` mengimpor `App\Models\Developer`:**  
   Signature `CatalogService::createProduct(array $data, User|Developer $actor)` dan `StockService::recordInitialStock(Product $product, User|Developer $actor)` menerima union type `User|Developer`.  
   -> **Implikasi:** Harus disederhanakan menjadi `User $actor` saja sebelum `App\Models\Developer` dihapus.
3. **`App\Services\OrderExpiryService` bergantung pada `InternalDispatcher` & provider `'simulator'`:**  
   Method `expireOverdueViaWebhook()` (dipanggil command terjadwal `ira:expire-overdue-orders` di `routes/console.php`) mengirimkan payload webhook ke `dispatchWebhook('simulator', ...)`.  
   -> **Implikasi:** Harus diubah agar memakai `expireOverdue()` langsung atau sinkronisasi status dengan API payment gateway riil.
4. **`App\Http\Requests\RegisterBuyerRequest` & `CheckConfigCommand` membaca domain simulasi:**  
   `RegisterBuyerRequest` menolak email berdomain `config('ira.sim_account_email_domain')`, dan `CheckConfigCommand` memvalidasi `SIM_ACCOUNT_EMAIL_DOMAIN` serta `config('simulation.*')`.
5. **`App\Providers\AppServiceProvider` mengikat kontrak simulasi saat boot:**  
   - `ProviderRegistry::validateDriver(...)` membuat aplikasi gagal start jika `PAYMENT_DRIVER` bukan `'simulator'`.
   - `QrRenderer::class` diikat ke `TextQrRenderer::class`.
   - `BiodataGenerator::class` diikat ke `PythonBiodataGenerator::class`.
6. **Constraint database `payments` mengunci provider simulator:**  
   Migrasi `2026_11_05_000010_create_payments_tables.php` memiliki constraint keras `CHECK (provider = 'simulator')` dan `CHECK (is_simulated = 1)`, serta `CHECK (origin IN ('normal', 'developer_simulation'))` di hampir semua tabel transaksi.

---

## 3. Peta Klasifikasi Komponen (Keep / Refactor / Hapus)

### 3.1 Komponen yang Dapat Dipertahankan / Direfaktor sebagai Inti E-Commerce
- **Auth Guard `web` & RBAC dasar:** `BuyerAuthController` (disederhanakan tanpa cabang `developer`), `EnsureRole`, `EnsureActiveAccount`, `PreventBackHistory`, `Authenticate` (hapus cabang `/developer`).
- **Katalog, Keranjang, Stok, Pesanan:**  
  - Model: `User`, `Address` (relevan jika nanti mendukung alamat pengiriman), `Category`, `Product`, `StockMovement`, `Cart`, `CartItem`, `Order`, `OrderItem`, `OrderStatusHistory`.  
  - Service: `CatalogService`, `CartService`, `StockService`, `OrderStateMachine`, `CheckoutService`, `OrderExpiryService`.  
  - Controller: `Shop\HomeController`, `Shop\CatalogController`, `Shop\CartController`, `Shop\OrderController`, `Shop\CheckoutController`, `Shop\ProfileController`, `Admin\CategoryController`, `Admin\ProductController`, `Admin\OrderController`, `Admin\BuyerAccountController`.
- **Abstraksi Pembayaran (Fondasi Gateway Riil):**  
  - `App\Payments\PaymentAdapter` (`createPayment`, `getPaymentStatus`, `handleCallback`, `cancelPayment`)
  - `App\Payments\ProviderRegistry`, `PaymentInstruction`, `PaymentStatus`, `CallbackResult`, `QrPayload`, `QrRenderer`
  - `App\Services\PaymentService`, `PaymentStateMachine`, `PaymentCallbackHandler`
  - `App\Http\Controllers\Api\WebhookController` (`POST /api/v1/webhooks/payments/{provider}`)
  - `App\Http\Controllers\Shop\PaymentInstructionController`, `Shop\PaymentController`, `Admin\PaymentController`

### 3.2 Komponen yang Wajib Dihapus (Tujuan #3: Role Developer & Seluruh Simulasi)
- **Auth & Middleware Simulator:** `App\Models\Developer`, `Auth\DeveloperAuthController`, `Http\Middleware\InternalDispatchOnly`, opsi `developer` di `config/auth.php`, `LoginRequest`, `BuyerAuthController`, `Authenticate`, dan `ProvisionAccountCommand`.
- **Routes & Controllers Developer:** seluruh grup rute `/developer/*` (`routes/web.php` baris 171–233), redirect `/developer/masuk`, seluruh 9 controller di `app/Http/Controllers/Developer/*`, `Concerns\ExecutesSimulationPreview`, `PageController::developerDashboard()`, serta 11 FormRequest simulasi (`Execute*`, `Preview*`, `Reset*`, `StoreSimulationAccountsRequest`).
- **Engine Simulasi & Biodata:** `app/Simulation/*` (18 file — kecuali `PeriodResolver` & `PeriodRange` jika dipindahkan ke `app/Support/`), `config/simulation.php`, `python/biodata/*` (10 file).
- **Model, Migrasi, Factory Simulasi:** `SimulationRun`, `SimulationAuditLog`, `PaymentHttpTrace` (atau diganti audit log webhook riil), `2026_11_05_000002`, `000003`, `000015`, `000016`, `DeveloperFactory`, `Simulation*Factory`, `PaymentHttpTraceFactory`.
- **Views Developer:** seluruh folder `resources/views/developer/` (23 file Blade: 14 halaman + 9 partial), shell `$isDeveloperShell` & `.sim-banner` di `layouts/app.blade.php`.
- **Payment Simulator:** `SimulatorPaymentAdapter`, `SignatureVerifier` (HMAC dummy `X-Simulator-Signature`), `InternalDispatcher`, `SimulatorGatewayController` + rute `/api/v1/simulator/payments`, `SimulationPageController` + rute `/pay/sim/{token}*`, `TextQrRenderer`, `TraceRecorder`, `PaymentTracePanel`, serta view `payments/sim-show.blade.php`, `payments/_panel.blade.php`, `payments/_trace.blade.php` (termasuk mekanisme popup tersembunyi 5 ketukan di `resources/js/app.js`).
- **Tes Simulator:** `SimulationGeneratorTest`, `SimulationM4Test`, `DeveloperCatalogTest`, `SpikesTest`, `SeededRandomTest`, `SimulationPlanTest`, `TimeDistributorTest`, serta bagian simulasi di `AuthM1Test`, `PaymentM3bTest`, `PaymentM3cTest`, `OrderM3aTest`, `ProvisionAccountTest`.

### 3.3 Lokasi Referensi Merek `"Ira"` & Domain Lama (Tujuan #2: Rebranding)
Semua titik yang menyebut `Ira`, `Ira Stationery`, `iraweb`, `ira:`, `IRA-`, atau asumsi toko alat tulis Gorontalo:
- **Konfigurasi & Artisan Commands:**  
  `config/ira.php`, `app/Console/Commands/CheckConfigCommand.php` (`ira:check-config`), `ExpireOverdueOrdersCommand.php` (`ira:expire-overdue-orders`), `ProvisionAccountCommand.php` (`ira:provision-account`), `routes/console.php`, `.env.example` (`SIM_ACCOUNT_EMAIL_DOMAIN=sim.ira-stationery.test`).
- **Domain Identifier:**  
  `app/Support/OrderNumber.php` (format nomor pesanan `IRA-YYMMDD-XXXXXX`).
- **Seeder:**  
  `database/seeders/CatalogSeeder.php` (kategori & produk alat tulis fiktif bertanda Ira Stationery).
- **Views & Copy UI:**  
  `resources/views/layouts/app.blade.php` (title default, sidebar brand mark `i.`, nama `Ira Stationery`, footer `Ira Stationery • Gorontalo • WITA`), `auth/masuk.blade.php`, `auth/daftar.blade.php`, `shop/home-index.blade.php`, `shop/checkout-show.blade.php`, `payments/instruction-show.blade.php`, `payments/struk-show.blade.php`, `payments/_pickup-instruction.blade.php`.

### 3.4 Aset UI yang Diganti Total (Tujuan #1: Minimalist UI)
- `resources/css/tokens.css` (token warna lavender `#6655F5`, latar `#E9EBF8`, bayangan ganda neumorphism `--shadow-xs..lg`, `--shadow-inset`).
- `resources/css/app.css` (4.018 baris CSS custom Light Neumorphism).
- `vite.config.js` (konfigurasi font Bunny `Instrument Sans`, `Manrope`, `Inter`).
- Seluruh file Blade di `resources/views/` (dan `resources/js/app.js` bagian animasi/chart styling/panel trace/sim-account-picker).

---

## 4. Temuan Selisih Kode vs Dokumen Baseline `iraweb`

1. **Profil & Alamat Pembeli sudah terimplementasi di kode** (`Shop\ProfileController`, `UpdateBuyerProfileRequest`, `UpdateBuyerAddressRequest`, tabel `addresses`, kolom `username`/`phone`/`gender`/`birthdate` di `users`, view `shop/profil-show.blade.php` & `profil-form.blade.php`, serta link `Profil` di navbar `layouts/app.blade.php`), padahal `PRD.md` §3.3 dan `UI.md` §15 dahulu melarangnya.
2. **Alur pengambilan di toko (`pickup_at` / `picked_up_at`)** ditambahkan belakangan (`000017`, `000018`, `CheckoutRequest`, `_pickup-instruction.blade.php`): pembeli wajib memilih jadwal ambil saat checkout dan tidak ada fitur pengiriman/ongkir.
3. **Label `SIMULASI` sudah dihapus dari UI Pembeli & Admin** (keputusan 2026-11-08) di view terbaru (`pesanan-index`, `akun-pembeli-index`, `checkout-show`, `instruction-show`), dan `Admin\AnalyticsController` mengirim `facts->build($dari, $sampai)` tanpa filter `origin`, meski beberapa query controller (`Admin\OrderController`, `BuyerAccountController`) masih menyimpan kondisi `when($request->input('asal'), ...)` dan tabel `payments`/`orders` masih menyimpan metadata `origin`.
4. **Panel teknis HTTP (`payments/_panel.blade.php`)** di UI pembeli/admin/simulator disembunyikan di balik popup tersembunyi 5 ketukan (`[data-trace-trigger]` di `resources/js/app.js`), bertentangan dengan `UI.md` §9.3/§10.4 versi awal yang menampilkannya terbuka.
5. **Alpine.js belum terpasang** di `package.json`, sementara `UI.md` menyebut penggunaannya.
6. **`PageController`** (`adminDashboard`, `developerDashboard`) sudah tidak dipakai oleh rute `/admin` maupun `/developer`, tetapi kelas dan `placeholder.blade.php` masih tersisa di repo.
