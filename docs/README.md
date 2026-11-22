# Indeks Dokumentasi Proyek

Direktori `docs/` memuat dua kelompok dokumen: **dokumen aktif untuk transformasi ke proyek e-commerce baru**, dan **dokumen baseline `iraweb`** yang menjelaskan source code hasil clone sebelum diubah.

---

## 1. Dokumen Aktif (Tujuan Proyek Baru)

| Dokumen | Isi Utama | Status |
| --- | --- | --- |
| [audit-iraweb.md](audit-iraweb.md) | Analisis faktual struktur repo `Etherveo/iraweb`, arsitektur saat ini, inventaris modul keep/refactor/hapus, ketergantungan lintas modul, dan selisih kode vs dokumen lama. | Aktif (acuan audit) |
| [roadmap-transformasi.md](roadmap-transformasi.md) | Rencana kerja bertahap (Fase 0–6) untuk 4 tujuan akhir: UI Minimalist, Rebranding, Hapus Developer/Simulasi, dan E-commerce Scope Ringkas + Payment Gateway Riil. | Aktif (rencana kerja) |
| [database-baru.md](database-baru.md) | Desain skema database baru dari nol (tanpa tabel/kolom developer & simulasi), strategi migrasi bersih, dan penyesuaian tabel `payments` untuk gateway riil. | Aktif (draf desain DB) |
| [ui-minimalist-spec.md](ui-minimalist-spec.md) | Spesifikasi UI Minimalist pengganti Light Neumorphism (`UI.md`): palet netral kontras tinggi, border tipis, tanpa bayangan ganda, 2 aktor saja. | Aktif (draf spesifikasi UI) |

---

## 2. Dokumen Baseline `iraweb` (Arsip Referensi Kode Saat Ini)

Selama source code belum dimodifikasi, file-file di bawah ini tetap menjadi referensi perilaku baseline `iraweb` (*Ira Stationery*):

| Dokumen | Keterangan Baseline |
| --- | --- |
| [PRD.md](PRD.md) | Spesifikasi 208 fitur `iraweb` (ACC, CAT, PRD, SHP, CRT, STK, ORD, EVT, PAY simulator, SIM developer, ANL FR-01–FR-06). |
| [blueprint.md](blueprint.md) | Arsitektur teknis `iraweb` (SQLite, WITA, 2 guard + 3 aktor, Payment Simulator + InternalDispatcher, Engine Simulator, kontrak servis Python). |
| [ira-system-workflow.md](ira-system-workflow.md) | Diagram UML (Use Case, Aktivitas, Sekuens, Kelas, Mesin Status) kondisi baseline `iraweb`. |
| [Blueprint-Workflow.md](Blueprint-Workflow.md) | Peta alur W1–W11 dan inventaris layar konseptual `iraweb`. |
| [discussion.md](discussion.md) | Catatan keputusan awal sistem simulator `iraweb`. |
| [payment-gateway-simulator.md](payment-gateway-simulator.md) | Spesifikasi simulator pembayaran `iraweb` (6 metode dummy, QR URL `/pay/sim/{token}`, panel HTTP trace). |
| [UI.md](UI.md) | Spesifikasi visual lama (*Light Neumorphism + Motion*, lavender `#6655F5`, 4 permukaan termasuk Panel Developer & Payment Simulator). |
| [UI-CLAUDE.md](UI-CLAUDE.md) | Catatan kerja UI lama (termasuk aturan popup 5-ketukan untuk panel HTTP tersembunyi). |
| [figma-mapping.md](figma-mapping.md) | Pemetaan frame Figma `Ira-IPPL` (export gambar lokal di-gitignore). |
| [DESIGN.md](DESIGN.md) | Panduan generik UI/UX (dapat tetap dipakai sebagai prinsip umum usability/aksesibilitas). |
