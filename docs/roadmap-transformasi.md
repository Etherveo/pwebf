# Roadmap Transformasi Proyek Baru

Dokumen ini memetakan langkah bertahap untuk mengubah baseline clone `Etherveo/iraweb` menjadi proyek e-commerce baru sesuai empat tujuan akhir pemilik proyek, **tanpa mengubah source code sebelum keputusan desain & scope disetujui**.

---

## Ringkasan 4 Tujuan Akhir + Kebijakan Database

1. **UI Minimalist Total:** Mengganti seluruh tampilan Light Neumorphism menjadi desain minimalis modern yang bersih, ringan, dan konsisten ([ui-minimalist-spec.md](ui-minimalist-spec.md)).
2. **Rebranding dari "Ira":** Menghapus seluruh identitas *Ira Stationery* / `iraweb` / prefix `ira:` / `IRA-` dan menggantinya dengan nama toko baru (sementara memakai placeholder `[Nama Toko TBD]` hingga nama final disepakati).
3. **Hapus Role Developer & Seluruh Simulasi:** Menghapus guard `developer`, tabel `developers`, panel `/developer/*`, mesin generator simulasi (`app/Simulation/*`), skrip biodata Python, serta simulator pembayaran internal (`/pay/sim/*`, `SimulatorPaymentAdapter`, `InternalDispatcher`).
4. **Scope E-Commerce Lebih Ringkas + Payment Gateway Riil:** Merampingkan ruang lingkup aplikasi agar fokus pada alur jual-beli inti serta mengintegrasikan payment gateway riil melalui kontrak `PaymentAdapter`.
5. **Database Benar-Benar Baru:** Tidak mempertahankan data atau file database lama dari `iraweb`; skema baru dibangun bersih dari nol ([database-baru.md](database-baru.md)).

---

## Keputusan Terbuka Sebelum Implementasi (Fase 0)

Sebelum masuk ke penulisan kode (Fase 1 ke atas), butir-butir berikut perlu diputuskan oleh pemilik proyek:

| ID | Topik | Opsi yang Tersedia | Catatan Dampak Teknis |
| --- | --- | --- | --- |
| **DEC-01** | **Nama Merek Baru** | Nama final sedang didiskusikan (pengganti *Ira Stationery*). | Memengaruhi `APP_NAME`, file konfigurasi pengganti `config/ira.php`, prefix nomor pesanan (`OrderNumber`), prefix command Artisan, serta seluruh copy UI. |
| **DEC-02** | **Scope Analitik (`python/analytics/` + FR-01..FR-06)** | **Opsi A:** Hapus servis Python & analitik statistik lanjut (Mann-Kendall, Wilson, dll.), ganti dengan ringkasan penjualan/order sederhana berbasis SQL di dashboard Admin.<br>**Opsi B:** Pertahankan `python/analytics/` + `events` hanya untuk data transaksi nyata (tanpa filter `origin`). | Opsi A jauh lebih sesuai dengan tujuan *"scope yang lebih kecil"* dan menghilangkan kebutuhan menjalankan 2 server (`php artisan serve` + `python server.py`). |
| **DEC-03** | **Model Pemenuhan Pesanan** | **Opsi A:** Tetap *pickup* di toko (`pickup_at` / `picked_up_at`, seperti saat ini).<br>**Opsi B:** Pengiriman ke alamat (`addresses` sudah ada di kode, tambah status `dikirim` & opsi ongkir).<br>**Opsi C:** Sederhanakan tanpa jadwal pickup wajib. | Menentukan apakah form `pickup_date`/`pickup_time` di checkout tetap ada atau diganti pilihan pengiriman/alamat. |
| **DEC-04** | **Provider Payment Gateway Riil** | Midtrans (Snap / Core API), Xendit (Invoice / QRIS / VA), Tripay, atau Duitku. | Menentukan implementasi class `*PaymentAdapter` baru, format payload/signature di `WebhookController`, dan konfigurasi env/sandbox key. |
| **DEC-05** | **Mesin Database Proyek Baru** | **SQLite** (file baru `database/database.sqlite`, nol setup server) **vs** **MySQL / MariaDB** (mis. via XAMPP lokal). | Migrasi `iraweb` lama banyak memakai raw SQL khusus SQLite; di skema database baru disarankan memakai Laravel Schema Builder standar agar portabel. |
| **DEC-06** | **Remote Repository Git Baru** | URL repo baru di akun GitHub `Etherveo`. | Mengarahkan `git remote set-url origin <url-baru>` pada direktori kerja ini. |

---

## Rencana Eksekusi Bertahap (Setelah Tahap Dokumentasi Disetujui)

### Fase 0 — Penyelarasan Keputusan & Remote Git
- Tetapkan keputusan **DEC-01 s.d. DEC-06**.
- Arahkan remote git direktori ini ke repository baru di akun `Etherveo` (`git remote set-url origin ...`).
- Jalankan `composer install` dan `npm install` serta siapkan `.env` baru.

### Fase 1 — Database Baru & Migrasi Bersih
- Mengacu ke [database-baru.md](database-baru.md).
- Buat database lokal yang benar-benar baru (tanpa membawa file/data `iraweb`).
- Susun ulang `database/migrations/` agar bersih dari tabel `developers`, `simulation_runs`, `simulation_audit_logs`, serta bersih dari kolom `origin`, `simulation_run_id`, `is_simulated`, dan `CHECK (provider = 'simulator')`.
- Sesuaikan tabel `payments` (provider dinamis, `external_id` / `provider_payment_id`, `checkout_url` / `payment_payload`, dsb.) dan seed katalog awal sesuai domain merek baru.

### Fase 2 — Pembersihan Role Developer & Seluruh Simulasi
- Hapus guard `developer` di `config/auth.php` dan seluruh middleware/redirect `/developer`.
- Pindahkan `PeriodResolver` & `PeriodRange` ke `app/Support/` jika masih dipakai oleh fitur laporan/analitik (atau hapus jika memilih Opsi A pada DEC-02).
- Hapus `app/Simulation/`, `app/Http/Controllers/Developer/`, `app/Http/Controllers/Concerns/ExecutesSimulationPreview.php`, `app/Http/Controllers/Auth/DeveloperAuthController.php`, `app/Models/Developer.php`, `SimulationRun.php`, `SimulationAuditLog.php`, `python/biodata/`, `config/simulation.php`, `resources/views/developer/`, serta seluruh FormRequest dan test simulasi.
- Lepaskan union type `User|Developer` di `CatalogService` dan `StockService`.
- Hapus subsistem payment simulator (`SimulationPageController`, `SimulatorGatewayController`, `InternalDispatcher`, `SimulatorPaymentAdapter`, `SignatureVerifier` dummy, `TraceRecorder`, `PaymentTracePanel`, `_panel.blade.php`, `sim-show.blade.php`, `TextQrRenderer`).
- Sederhanakan form login `/masuk` menjadi 2 aktor saja (`Pembeli` | `Admin Toko`) atau pisahkan login admin bila diinginkan.

### Fase 3 — Rebranding & Penyederhanaan Domain
- Ganti `config/ira.php` menjadi nama konfigurasi netral (mis. `config/store.php` atau sesuai nama merek baru).
- Perbarui prefix `OrderNumber` (`IRA-` -> prefix merek baru), nama artisan commands (`ira:*` -> prefix baru), footer, title HTML, dan seluruh copy Blade menggunakan `config('app.name')`.
- Sesuaikan `CatalogSeeder` dengan produk nyata/contoh milik merek baru.
- Terapkan keputusan **DEC-02** (analitik sederhana vs Python) dan **DEC-03** (pickup vs pengiriman).

### Fase 4 — Pergantian Total UI ke Minimalist
- Mengacu ke [ui-minimalist-spec.md](ui-minimalist-spec.md).
- Ganti `resources/css/tokens.css` dan `resources/css/app.css` (buang 4.018 baris Neumorphism lavender & bayangan ganda, bangun ulang dengan sistem token minimalis yang ringkas berbasis Tailwind v4 + komponen Blade reusable).
- Rapikan `resources/js/app.js` (buang script simulator picker, preset funnel, reset phrase, dan hidden 5-tap trace popup).
- Redesign seluruh Blade views untuk **Storefront Pembeli**, **Auth**, **Admin Toko**, dan **Halaman Pembayaran/Struk** pada 3 ukuran layar (`~390px`, `768px`, `1280px`).

### Fase 5 — Integrasi Payment Gateway Riil
- Pertahankan arsitektur kontrak `App\Payments\PaymentAdapter`, `ProviderRegistry`, `PaymentService`, `PaymentCallbackHandler`, `OrderStateMachine`, dan `StockService`.
- Implementasikan adapter baru untuk gateway pilihan (**DEC-04**, mis. `MidtransPaymentAdapter` atau `XenditPaymentAdapter`) beserta verifikasi signature webhook asli di `WebhookController` (`POST /api/v1/webhooks/payments/{provider}`).
- Perbarui halaman checkout & instruksi pembayaran untuk mendukung alur gateway riil (Snap popup / redirect payment URL / QRIS resmi / VA resmi dari respons API gateway).
- Perbarui `OrderExpiryService` agar sinkron dengan expiry gateway (baik via webhook expiry dari gateway maupun cek status API saat akses/scheduler).
- Uji end-to-end di mode **Sandbox** gateway (create payment, webhook signature valid/invalid, idempotensi callback ganda, paid, failed, expired, cancel).

### Fase 6 — Pembaruan Automated Tests & Dokumentasi Final
- Tulis ulang/perbarui suite Pest untuk mencakup alur auth 2 aktor, katalog, keranjang, checkout, pembatalan, stok concurrency/anti-oversell, dan webhook payment gateway (mock HTTP & sandbox contract).
- Arsipkan/hapus dokumen legacy `iraweb` yang sudah tidak relevan dan jadikan dokumentasi baru sebagai sumber kebenaran tunggal.
