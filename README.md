# E-Commerce Minimalis (Transformasi dari Baseline `iraweb`)

> **Status saat ini:** **Fase Analisis & Dokumentasi (Source code belum diubah).**  
> Direktori ini merupakan hasil clone dari [`Etherveo/iraweb`](https://github.com/Etherveo/iraweb) dan disiapkan untuk diarahkan ke repository baru di akun GitHub yang sama. Seluruh source code aplikasi masih berada pada baseline `iraweb` (*Ira Stationery*) sampai tahap refactoring dimulai.

---

## Tujuan Akhir Proyek Baru

1. **Pergantian total UI ke Minimalist** — mengganti sistem visual *Light Neumorphism + Motion* (`iraweb`) menjadi antarmuka minimalis yang bersih, datar/berbatas tipis, kontras tinggi, dan bebas efek bayangan ganda.
2. **Rebranding dari "Ira" ke nama toko baru** — identitas merek *"Ira Stationery"* sedang dalam proses diskusi dan akan digantikan secara terpusat melalui konfigurasi (`APP_NAME` / konfigurasi toko).
3. **Menghapus role `developer` dan seluruh subsistem simulasi** — menghapus panel developer, mesin generator transaksi/akun simulasi, skrip biodata Python, halaman simulator pembayaran, dan metadata simulasi.
4. **Scope e-commerce lebih ringkas + integrasi Payment Gateway riil** — memfokuskan aplikasi sebagai web e-commerce yang lebih ramping untuk **Pembeli** dan **Admin Toko**, serta mengganti `SimulatorPaymentAdapter` dengan adapter payment gateway riil (memanfaatkan kontrak `App\Payments\PaymentAdapter` dan `WebhookController` yang sudah ada).
5. **Database benar-benar baru** — tidak mewarisi atau memigrasi basis data SQLite milik `iraweb`; skema dibangun ulang dari nol tanpa tabel/kolom simulasi dan siap untuk mesin basis data yang dipilih pada proyek baru.

---

## Peta Dokumentasi

### Dokumen Aktif (Proyek Baru & Rencana Transformasi)
- [docs/README.md](docs/README.md) — Indeks dokumentasi aktif vs arsip baseline `iraweb`.
- [docs/audit-iraweb.md](docs/audit-iraweb.md) — Audit lengkap isi repo `iraweb` (arsitektur, inventaris file, ketergantungan lintas modul, dan temuan selisih kode vs dokumen lama).
- [docs/roadmap-transformasi.md](docs/roadmap-transformasi.md) — Rencana bertahap (Fase 0–6) beserta keputusan terbuka sebelum mengubah source code.
- [docs/database-baru.md](docs/database-baru.md) — Rancangan skema database baru dari nol (tanpa simulasi/developer, siap payment gateway riil).
- [docs/ui-minimalist-spec.md](docs/ui-minimalist-spec.md) — Spesifikasi sistem visual Minimalist pengganti *Light Neumorphism*.
- [AGENTS.md](AGENTS.md) & [CLAUDE.md](CLAUDE.md) — Aturan kerja agen AI (keduanya cermin identik).

### Dokumen Baseline Lama (`iraweb` — Hanya Referensi Kode Saat Ini)
Dokumen-dokumen berikut tetap disimpan di `docs/` sebagai peta kode `iraweb` sebelum refactoring dimulai:
- `docs/PRD.md`, `docs/blueprint.md`, `docs/Blueprint-Workflow.md`, `docs/ira-system-workflow.md`
- `docs/discussion.md`, `docs/payment-gateway-simulator.md`
- `docs/UI.md`, `docs/UI-CLAUDE.md`, `docs/figma-mapping.md`, `docs/DESIGN.md`

---

## Kondisi Clone Saat Ini (Belum Diinstalasi / Belum Dimodifikasi)

- **Remote Git saat ini:** `origin -> https://github.com/Etherveo/iraweb` (belum diarahkan ke remote baru).
- **Dependensi & environment lokal:** `vendor/`, `node_modules/`, `.env`, dan file database (`database/*.sqlite`) **belum ada** di hasil clone ini.
- **Stack baseline yang terpasang di manifest:**
  - PHP `^8.3` & Laravel Framework `^13.17` (`composer.json`)
  - Vite `^8.0`, Tailwind CSS `^4.0`, `chart.js ^4.6.1`, `qrcode ^1.5.4` (`package.json`)
  - Pest `^5.3` untuk pengujian otomatis (`phpunit.xml`)
  - Python `python/analytics/` (servis HTTP internal port `8001`) dan `python/biodata/` (generator akun simulasi — target hapus).

> **Catatan aturan kerja:** Jangan mengubah source code terlebih dahulu sebelum keputusan desain/scope di [docs/roadmap-transformasi.md](docs/roadmap-transformasi.md) disepakati. Saat database disiapkan nanti, buatlah database baru dari awal sesuai [docs/database-baru.md](docs/database-baru.md).
