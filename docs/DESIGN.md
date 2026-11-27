# DESIGN.md — Aturan UI/UX Proyek E-Commerce `Cloraamine` (Versi Diluruskan)

> **Status Dokumen:** Versi `DESIGN.md` yang sudah diluruskan dari `DESIGN.md` v2 agar selaras 100% dengan codebase aktif **Cloraamine** (`resources/css/tokens.css`, `docs/ui-minimalist-spec.md`, `docs/roadmap-transformasi.md`, `config/store.php`) pada branch `feat/cloraamine-ecommerce`.

---

## Daftar Perubahan dari `DESIGN.md` Asli (v2)

Berikut ringkasan perubahan yang dibuat terhadap `DESIGN.md` (v2) asli beserta alasannya:

1. **Menambahkan Bagian Authority Order & Penonaktifan Preset Biru (§4, §12):**
   - Menetapkan urutan prioritas keputusan visual secara eksplisit: **(1)** `resources/css/tokens.css` + `docs/ui-minimalist-spec.md` → **(2)** instruksi eksplisit di task → **(3)** Appendix G → **(4)** defaults `DESIGN.md`.
   - Menandai **Preset "Modern product" biru (`#2563EB`) di §4 dan starter token biru di Appendix C TIDAK dipakai** pada proyek ini karena bertentangan dengan palet resmi proyek (**Warm Cream & Deep Espresso**).
2. **Mengisi Appendix G (Style Override) dengan Style Proyek yang Sudah Fix:**
   - Mengisi seluruh parameter visual dari `tokens.css` & keputusan desain aktif: **Warm Cream & Deep Espresso** (`#FDF6EE` kanvas, `#FFFDF9` kartu, `#F6EBDD` inset/tombol sekunder, `#33200F` espresso untuk teks & navigasi sidebar aktif, `#9A4D18` karamel untuk tombol utama `.btn-primary` Opsi A + hover `#7C3C11`), font `Plus Jakarta Sans` / `Inter` / `JetBrains Mono`, radius `4px–12px` + pill, shadow hangat espresso, bahasa Indonesia, format `Rp 1.250.000` & tanggal Indonesia (`WITA`), serta **tema terang saja (light only)**.
3. **Mengganti Appendix H (Stack Output Kode):**
   - Menghapus default **React + TypeScript + Tailwind CSS + shadcn/ui + Lucide + Recharts** dan menggantinya dengan stack riil proyek ini: **Laravel Blade + Tailwind CSS v4 + CSS custom tokens (`tokens.css` & `app.css`) + Vite + Chart.js + `qrcode` + Pest**.
   - Menghapus/menandai **tidak berlaku** instruksi untuk **Figma Make, Google Stitch, v0, dan Lovable** di pembuka file (hanya disimpan catatan bahwa rujukan eksternal tersebut tidak dipakai sebagai generator kode di repo ini).
4. **Mengunci Scope & Domain Proyek (§2, §12, §13):**
   - Menegaskan batas pekerjaan UI: **hanya meningkatkan halaman yang sudah ada**, dilarang meng-invent entitas, field, fitur, endpoint, peran, migrasi, atau skema database baru (`docs/database-baru.md`), serta dilarang mengubah alur jual-beli atau meng-hardcode nama toko (wajib dari `config/store.php`, default `Cloraamine`).
   - Menegaskan proyek hanya memiliki **2 aktor** (`Pembeli` dan `Admin Toko`, tanpa `Developer` dan tanpa simulasi) serta **2 metode pemenuhan** (`Ambil di Toko` / pickup dan `Kirim via Ekspedisi` / kurir).
5. **Menetapkan Dials Resmi Proyek (§3, §4, Appendix A):**
   - **Admin (`admin_toko`):** **D7 / E3 / M2** (Density 7 · Expressiveness 3 · Motion 2).
   - **Storefront e-commerce (`pembeli` & tamu):** **D5 / E5 / M4** (Density 5 · Expressiveness 5 · Motion 4).
6. **Membersihkan Pengecualian & Contoh yang Tidak Relevan (§10, §13, Appendix A/C/D):**
   - Menghapus pengecualian animasi `/intro` cinematic di §10 karena tidak ada halaman `/intro` di proyek ini (seluruh halaman wajib menghormati `prefers-reduced-motion`).
   - Memperbarui contoh `Design Notes` di §13 agar merujuk ke token dan dial resmi `Cloraamine`, bukan `#0F766E` / slate generic.

---

## Cara Menggunakan File Ini

- **Berlaku untuk repo ini (Laravel Blade):** Agen coding (`AGENTS.md` / `CLAUDE.md` / Cursor / Gemini) membaca file ini bersama `resources/css/tokens.css` dan `docs/ui-minimalist-spec.md`.
- **Alat eksternal tidak berlaku:** Petunjuk khusus **Figma Make (`guidelines/Guidelines.md`)**, **Figma Design Agent Skill**, **Google Stitch**, **v0**, dan **Lovable** pada `DESIGN.md` v2 **tidak berlaku** di proyek ini (maksimal sebagai referensi konseptual umum saja, jangan pernah menghasilkan komponen React/shadcn atau instruksi tool tersebut).

**Tipe aturan** — `[HARD]` wajib dipatuhi. `[DEFAULT]` adalah standar kerja; penyimpangan hanya boleh bila diinstruksikan secara eksplisit oleh task atau `tokens.css`/`ui-minimalist-spec.md`, dan harus dicatat satu baris. `[CHECK]` diverifikasi sebelum layar dinyatakan selesai.

---

# PART 1 — CORE RULES

## 1. Priorities `[HARD]`

Selesaikan konflik secara top-down — tingkat lebih tinggi selalu mengalahkan yang lebih rendah:

1. **Kepatuhan spesifikasi sistem & kontrak codebase** — skema, rute, form, validasi, role, dan alur jual-beli yang sudah ada adalah sumber kebenaran mutlak (§2). `[HARD]`
2. **Usability & batas minimum aksesibilitas** (§8, §10). `[HARD]`
3. **Kualitas visual** — hierarki, kontras, kejelasan tombol aksi, kerapian tata letak, dan konsistensi (§5–§7). Tampilan yang benar secara fungsional tetapi terasa polos, tombolnya kabur, atau layoutnya timpang dianggap belum selesai.
4. **Karakter merek** — nuansa hangat **Warm Cream & Deep Espresso** milik `Cloraamine`, tidak boleh mengorbankan poin 1–3.
5. **Minimalisme terukur** — bersih bukan berarti kosong/polos; kesederhanaan hanya bekerja bila hierarki permukaan, batas section, dan affordance tombol dieksekusi dengan rapi.

## 2. Spec & Scope Fidelity `[HARD]`

Codebase aktif (`routes/web.php`, Controller, FormRequest, Model, `config/store.php`, dan `docs/database-baru.md`) menentukan seluruh perilaku sistem. Lapisan UI tidak boleh membengkokkan atau menambah fungsi di luar itu:

- **Scope ketat proyek ini:**
  - **Hanya tingkatkan UI pada halaman-halaman yang sudah ada.**
  - **Jangan invent** entitas, field form baru, endpoint baru, permission baru, migrasi, atau perubahan skema database.
  - **Jangan ubah alur jual-beli**, logika stok/checkout/pembayaran, atau mengganti sumber nama toko (`config('store.name')` / `config('store.*')`, default `Cloraamine`).
  - **Aktor hanya 2:** `Pembeli` dan `Admin Toko` (guard `web`). Tidak ada aktor `Developer`, tidak ada panel simulasi, tidak ada badge/banner simulasi.
  - **Metode pemenuhan hanya 2:** `Ambil di Toko` (`pickup_at`, `picked_up_at`) dan `Kirim via Ekspedisi` (kurir `JNE`/`J&T`/`SiCepat`, ongkir, alamat, nomor resi).
- Setiap field wajib, batas validasi (min, max, format), pesan error inline (`aria-describedby`), nilai enum/status, dan hak akses per role harus tetap tampil dan bekerja sesuai implementasi saat ini.
- Gunakan kosakata domain yang sudah ada apa adanya dalam bahasa Indonesia (`Keranjang`, `Checkout`, `Ambil di Toko`, `Kirim via Ekspedisi`, `Pre-Order`, `Bekas`, `Unggulan`, `Funnel Pembelian`, `Aktivitas Produk`).
- Yang bebas dan wajib ditingkatkan oleh UI adalah **presentasi**: tata letak grid/kolom, pengelompokan kartu, kejelasan tombol & aksi baris, ikon SVG konsisten, tipografi, hierarki permukaan, responsivitas (`390px`, `768px`, `1280px`), dan microcopy penjelas yang tidak mengubah kontrak data.

## 3. Understand Before You Draw `[DEFAULT]`

Sebelum mengubah tampilan, tetapkan hal berikut:

1. **Produk & pengguna:** Toko e-commerce merchandise & cetak custom (`Cloraamine`) dengan 2 permukaan: **Storefront** (pengunjung & pembeli di ponsel/desktop) dan **Panel Admin Toko** (pengelola toko di desktop/tablet/mobile).
2. **Register:**
   - **Storefront** = *Product (E-Commerce)* dengan sentuhan *Editorial/Warm* di hero beranda (`/`) tanpa mengubahnya menjadi landing page pemasaran penuh klaim fiktif.
   - **Admin Toko** = *Product (Back-Office & Analytics)*.
3. **Prioritas informasi per layar:** Pastikan judul halaman + status/aksi utama terbaca pertama, kontrol filter terkelompok rapi kedua, dan daftar produk / tabel / ringkasan checkout dominan dan nyaman dipindai.
4. **Model navigasi yang berlaku:**
   - Storefront: Topbar sticky full-width (`Katalog`, plus `Keranjang`, `Pesanan`, `Pembayaran`, `Profil`, `Keluar` saat login pembeli; `Masuk` dan `Daftar Pembeli` saat tamu) + drawer menu di mobile `< 768px`.
   - Admin Toko: CSS Grid `.admin-shell` dengan sidebar kiri sticky penuh di desktop (`>= 1024px`) tanpa topbar duplikat + drawer di mobile `< 1024px`.
5. **Style & token:** Ikuti `resources/css/tokens.css`, `docs/ui-minimalist-spec.md`, dan Appendix G di bawah secara mutlak.
6. **Komponen yang digunakan ulang:** Manfaatkan class dan partial yang sudah ada (`tokens.css`, `app.css`, `_period-filter.blade.php`, `_pickup-instruction.blade.php`, `vendor/pagination/tailwind.blade.php`) sebelum menambah class baru.

## 4. Style & Dials Resmi Proyek `[DEFAULT]`

Proyek ini **sudah memiliki sistem gaya tetap** — **jangan** melakukan inferensi ulang atau memakai preset biru generic.

> **Penegasan:** **Preset "Modern product" biru di `DESIGN.md` v2 TIDAK dipakai pada proyek ini.**

**Dials Resmi Cloraamine:**

| Permukaan | Archetype Acuan | Density (D) | Expressiveness (E) | Motion (M) |
| --- | --- | :---: | :---: | :---: |
| **Admin Toko** (`/admin/*`) | Admin / back-office + Analytics SaaS | **7** | **3** | **2** |
| **Storefront E-Commerce** (`/`, `/katalog`, `/produk/*`, alur pembeli & auth) | E-commerce / catalog hangat | **5** | **5** | **4** |

- **Admin (D7 / E3 / M2):** Padat-terstruktur untuk tabel operasional, filter analitik, dan form CRUD; visual tenang dengan penekanan jelas pada tombol aksi utama vs tombol baris tabel; gerak hanya umpan balik interaksi (`150ms`).
- **Storefront (D5 / E5 / M4):** Seimbang dan lapang agar foto/kartu produk menonjol di atas kanvas `#FDF6EE`; kartu produk ber-hover halus, hero editorial espresso `#33200F`, aksen karamel `#9A4D18` pada CTA, serta alur keranjang/checkout/pembayaran yang jelas langkahnya.

## 5. Layout, Density and Whitespace `[DEFAULT]`

- **Proximity:** jarak di dalam kelompok (`8–16px`) < antar kelompok (`20–28px`) < antar section (`32–48px`).
- **Page & card padding:** gunakan padding container yang proporsional (`16px` mobile, `24px` tablet, `24–32px` desktop) dan `16–24px` untuk `.card`. Jangan ada teks atau kontrol yang menempel tepi container.
- **Tanpa void (ruang kosong tak beralasan):**
  - Pada **Admin** (`.admin-shell`), kolom kanan `.admin-workspace` mengisi penuh ruang tersisa (`max-width: 84rem`) dengan tepi `.admin-page` dan `.site-footer-inner` sejajar persis. Jangan mengapungkan kolom sempit di tengah layar lebar kecuali untuk form khusus `.admin-page-narrow` (`58rem`) atau kartu auth.
  - Pada kolom samping/kanan split layout (`.buyer-split-layout` di keranjang, checkout, detail pesanan, profil), hindari aside yang terlalu pendek meninggalkan void besar; buat sticky atau isi ringkasan pendukung yang memang berasal dari data halaman tersebut.
- **Tanpa dinding teks:** pecah informasi ke KPI box, daftar key-value (`.admin-kv-list`), chip status, badge atribut, dan tabel yang rapi.
- **Ritme & lapisan permukaan 3-lapis:** Kanvas (`--bg-page: #FDF6EE`) → Kartu/Permukaan (`--bg-surface: #FFFDF9`) → Inset/Tombol Sekunder (`--bg-subtle: #F6EBDD`) dengan border `--border-subtle` / `--border-hairline`. Hindari belang zebra tabel yang terlalu pekat agar isi tabel tetap mudah dipindai.

## 6. Visual Richness & Kejelasan Tombol `[DEFAULT]`

- **Hierarki tombol tegas (Opsi A):**
  - **Primary CTA (`.btn-primary`, `.topbar-cta`):** Karamel `#9A4D18` (`--brand`), teks `#FFFDF9`, hover `#7C3C11` — selalu jelas berbeda dari navigasi aktif sidebar (`#33200F` Deep Espresso).
  - **Secondary & Compact Action (`.btn-secondary`, `.btn-ghost-sm`, `.admin-action-link`):** Latar terisi `--btn-secondary-bg` (`#F6EBDD`) dengan border jelas (`--border-strong` / `--border-subtle`) dan teks `#33200F`, sehingga selalu terbaca sebagai tombol di atas kartu `#FFFDF9`. Aksi di kolom `Aksi` tabel tidak boleh tampil sebagai link teks garis bawah polos.
  - **Destructive (`.btn-danger-ghost`):** Latar lembut `#FEF2F2`, border `#FCA5A5`, teks `#991B1B`.
- **Ikon SVG konsisten:** Gunakan SVG inline bergaya stroke bersih (`1.8`–`2`, ukuran `16px`/`18px`/`20px`/`32px`) pada navigasi sidebar, tombol aksi utama, tombol kembali, state kosong, dan kartu produk. Hindari mencampur ikon Unicode (`☰`, `×`) dengan SVG di header/drawer.
- **Gambar & placeholder produk:** Tampilkan foto produk asli bila `hasImage()` bernilai true; bila belum ada foto, tampilkan tile ikon di atas `--bg-media: #EFE2D2` dengan tag kategori dan atribut (`Pre-Order` / `Bekas` / `Unggulan`) yang terstruktur.
- **Visualisasi data:** Gunakan Chart.js di halaman `/admin`, `/admin/funnel`, `/admin/aktivitas` dengan palet warna Warm Cream & Deep Espresso yang sudah dikonfigurasi di `resources/js/app.js`.
- **Status & umpan balik:** Chip status selalu memadukan warna semantik + teks label (+ ikon bila relevan). Angka harga `Rp`, kuantitas, VA, dan kode referensi wajib `tabular-nums` dan `white-space: nowrap` pada sel harga tabel agar tidak patah 2 baris.

## 7. Tokens `[HARD — values come from tokens]`

Seluruh nilai warna, font, radius, shadow, dan transisi **wajib** berasal dari `resources/css/tokens.css` (dan tidak meng-hardcode hex ad-hoc di Blade atau `app.css` di luar token yang ada). Lihat nilai lengkap di **Appendix G** dan `docs/ui-minimalist-spec.md`.

## 8. Responsive Design `[HARD requirement — behaviors below are DEFAULT]`

Uji dan rancang seluruh halaman pada **3 breakpoint utama**:

| Breakpoint | Lebar Uji | Perilaku Tata Letak Proyek |
| --- | --- | --- |
| **Mobile** | `~390px` (`360–767px`) | 1 kolom; topbar storefront menjadi drawer; sidebar admin menjadi drawer slide-over; kategori katalog ringkas/horizontal agar produk cepat terlihat; tabel `.admin-table-wrap` berubah menjadi kartu key-value berlabel `data-label`; `.pager-mobile` tampil; target sentuh `>= 44×44px`. |
| **Tablet** | `768px` (`768–1023px`) | Sidebar admin masih drawer (`< 1024px`); layout split/filter membungkus rapi tanpa celah tombol yatim; grid produk 2–3 kolom; filter tanggal & kategori tersusun proporsional. |
| **Desktop** | `1280px+` (`1024px+`) | Admin memakai CSS Grid `.admin-shell` (`16rem minmax(0, 1fr)`) dengan sidebar sticky `100vh`; Storefront `max-width: 72rem`; `.buyer-split-layout` 2 kolom (`1.6fr / 1fr`) dengan summary sticky; kategori katalog sticky di kiri; tabel tanpa overflow horizontal halaman. |

## 9. Components and States `[DEFAULT]`

Gunakan kembali komponen dan class di `tokens.css` / `app.css` sebelum membuat class baru. Setiap kontrol interaktif wajib mendukung state nyata:
- **Tombol:** default, hover, active, `focus-visible` (outline karamel `2px`), `disabled` (`opacity: 0.45`).
- **Input / Select / Textarea:** default, hover, focus (ring `rgba(154, 77, 24, 0.2)`), filled, `aria-invalid="true"` + pesan error inline, `disabled`/`readonly`.
- **Daftar & Tabel:** populated, empty (`.admin-empty` / `.shop-empty` dengan CTA jelas kembali/reset/tambah), hasil pencarian kosong, dan pagination (`.pager*`).
- **Navigasi:** default, hover, `aria-current="page"` / `.is-active`, drawer mobile terbuka/tertutup.

## 10. Accessibility `[HARD]`

- Kontras teks body minimal `4.5:1`; teks besar dan batas kontrol/ikon bermakna minimal `3:1`.
- Warna bukan satu-satunya penanda status (selalu sertakan teks label/ikon).
- Fokus keyboard `:focus-visible` jelas pada semua tombol, link, input, segmented radio, dan tombol modal.
- Label form selalu terlihat di atas input (`<label for="...">`), bukan hanya placeholder; error terhubung lewat `aria-describedby`.
- Target sentuh di layar sentuh `>= 44×44px`.
- Seluruh transisi dan grafik Chart.js menghormati `@media (prefers-reduced-motion: reduce)` (tanpa pengecualian apa pun).

## 11. Content, Language and UX Writing `[DEFAULT]`

- **Bahasa & lokal:** 100% Bahasa Indonesia baku yang natural dan ramah untuk e-commerce.
- **Format angka, uang, waktu:** Rupiah `Rp 1.250.000`, tanggal `27 Nov 2026 16:00 WITA` (`Asia/Makassar`), nomor HP Indonesia (`0812...`).
- **Label aksi spesifik:** Gunakan kata kerja tindakan yang jelas (*"Tambah ke Keranjang"*, *"Lanjut ke Checkout"*, *"Buat Pesanan"*, *"Konfirmasi Pembayaran"*, *"Simpan perubahan"*, *"Bersihkan pencarian"*).
- **Anti lorem ipsum & klaim fiktif:** Gunakan data domain `Cloraamine` (photocard, album, merchandise, stiker custom, pin button custom). Jangan menambah testimoni, logo mitra, atau angka pengguna fiktif.

## 12. Scope, Conflicts and Authority Order `[DEFAULT]`

**Authority order keputusan visual di proyek ini (tertinggi ke terendah):**
1. **`resources/css/tokens.css` + `docs/ui-minimalist-spec.md`** (sumber kebenaran token & spesifikasi UI aktif di codebase).
2. **Instruksi eksplisit pemilik proyek (Ren) pada task aktif.**
3. **Appendix G (Style Override)** di dokumen ini.
4. **Aturan default Bagian 1 & Bagian 2 `DESIGN.md` ini.**

Aturan kerja tambahan:
- **Jangan mengubah preset/warna seenaknya** di tengah jalan; semua sesi/room chat harus konsisten mengikuti token yang sama.
- Jika menemukan celah teknis/keputusan produk saat merencanakan atau mengaudit UI, **tulis sebagai Open Question** untuk dikonfirmasi, jangan mengasumsikan atau menambah fitur backend secara diam-diam.

## 13. Review and Delivery `[CHECK]`

Sebelum menyatakan perbaikan UI selesai, periksa seluruh layar pada lebar `390px`, `768px`, dan `1280px`:

- [ ] **Spec & Scope** — tidak ada fitur/entitas/aktor baru yang di-invent; copy brand berasal dari `config('store.*')`; seluruh form, route, dan asersi tes tetap utuh.
- [ ] **Kejelasan Tombol** — tombol CTA utama (`#9A4D18`), tombol sekunder (`#F6EBDD`), tombol destruktif (`#FEF2F2`), dan tombol aksi baris (`32px`) terbaca jelas sebagai tombol.
- [ ] **Layout & Tanpa Void** — `.admin-shell` sejajar penuh antara sidebar, konten, dan footer; tidak ada celah kosong janggal atau tabel patah 2 baris pada harga.
- [ ] **Konsistensi** — hanya memakai token `tokens.css`, satu gaya ikon SVG, radius & shadow konsisten, tidak ada inline style ad-hoc yang tidak perlu.
- [ ] **Responsif & A11y** — rapi di `390px`, `768px`, `1280px`; kontras, fokus keyboard, label form, dan `prefers-reduced-motion` terpenuhi.
- [ ] **Verifikasi** — `npm.cmd run build` dan `php artisan test` lulus setelah perubahan terakhir.

**Format Design Notes proyek Cloraamine:**

```text
Design Notes
- Register · archetype: Admin (Product · Back-Office D7/E3/M2) | Storefront (Product · E-Commerce D5/E5/M4)
- Tokens: Warm Cream & Deep Espresso — canvas #FDF6EE · surface #FFFDF9 · inset #F6EBDD · espresso #33200F · caramel CTA #9A4D18
- Type & Shape: Plus Jakarta Sans + Inter + JetBrains Mono (tabular-nums) · radius 6/8/10/12px · warm espresso shadows
- Scope check: 2 aktor (Pembeli, Admin Toko) · 2 pemenuhan (pickup, kurir) · tanpa fitur/skema baru
```

---

# PART 2 — REFERENCE APPENDICES

*(Appendix A–F dan I dipertahankan sebagai referensi prinsip & pola dari `DESIGN.md` v2 dengan penyesuaian dial resmi proyek; **Appendix C biru TIDAK dipakai sebagai palet proyek ini**; **Appendix G & Appendix H telah diganti penuh** sesuai proyek `Cloraamine`.)*

## Appendix A — Archetypes and Dials (Acuan Terkunci Proyek)

- **Admin Toko (`admin_toko`):** **D7 / E3 / M2** — mengacu arketipe *Admin / Back-Office + Analytics SaaS*, disesuaikan ke tema *Warm Cream & Deep Espresso* dengan kepadatan tabel yang tetap terbaca.
- **Storefront E-Commerce (`pembeli` & tamu):** **D5 / E5 / M4** — mengacu arketipe *E-Commerce / Catalog*, disesuaikan ke tema *Warm Cream & Deep Espresso* dengan aksen tombol karamel `#9A4D18`.

## Appendix B — Mapping Elemen Sistem yang Sudah Ada

- **Entitas aktif:** Produk, Kategori Produk, Keranjang, Pesanan, Pembayaran, Akun Pembeli, Alamat Pembeli, Riwayat Stok, Event Aktivitas.
- **Navigasi:** Storefront topbar (≤ 6 item saat login, drawer mobile `< 768px`); Admin sidebar 4 kelompok (`RINGKASAN`, `TOKO`, `TRANSAKSI`, `AKUN`, drawer mobile `< 1024px`).

## Appendix C — Catatan Token Referensi (Tidak Dipakai)

> **Catatan:** Starter token preset biru (`#2563EB` / `#F8FAFC`) dari `DESIGN.md` v2 asli **dinonaktifkan** untuk proyek ini. Gunakan `resources/css/tokens.css` dan **Appendix G** di bawah.

## Appendix D — Screen Recipes (Ringkasan Pola Layar Proyek)

- **Auth (`/masuk`, `/daftar`):** Split desktop (`.auth-brand` gelap `#33200F` di kiri + `.auth-card` `#FFFDF9` di kanan); mobile bertumpuk rapi.
- **Beranda (`/`):** Hero editorial gelap `#33200F` + section produk (`Terlaris` kondisional, `Terbaru`, `Teratas`) dipisahkan `.home-section-head` beraksen kiri `#9A4D18`.
- **Katalog (`/katalog`) & Detail (`/produk/{id}`):** Filter toolbar di atas + `.shop-layout` (sidebar kategori sticky di kiri desktop + grid produk di kanan) + pagination `.pager*`.
- **Keranjang, Checkout, Pembayaran:** `.buyer-split-layout` 2 kolom (daftar/instruksi di kiri, ringkasan/countdown sticky di kanan); halaman hasil & struk cetak `@media print` 1 kolom terfokus.
- **Admin (`/admin/*`):** `.admin-head` terbingkai aksen kiri + filter + KPI/grafik Chart.js atau tabel operasional bersih + pagination `.pager*`.

## Appendix E — Transitions

- Hover tombol, kartu, link: `150ms cubic-bezier(0.2, 0.8, 0.2, 1)` (`--transition-fast`).
- Drawer sidebar/topbar & modal tamu: `150ms`, nonaktif saat `prefers-reduced-motion: reduce`.

## Appendix F — Translating Feedback

- "Terlalu polos / kurang kontras" → perkuat pemisahan 3-lapis permukaan (`#FDF6EE` / `#FFFDF9` / `#F6EBDD`), border `--border-subtle` (`#D8C3AC`), dan aksen kiri `--brand` (`#9A4D18`).
- "Ngga jelas mana tombolnya" → pisahkan warna CTA primer (`#9A4D18`) dari navigasi aktif (`#33200F`), beri latar `#F6EBDD` pada tombol sekunder, dan ubah semua aksi baris tabel menjadi tombol kompak.
- "Layout berantakan / terasa kosong" → samakan alignment `.admin-shell` + footer, rapikan lebar kolom tabel agar harga tidak patah 2 baris, dan sejajarkan proporsi panel split.

## Appendix G — Style Override (`Cloraamine` — WAJIB DIIKUTI)

```text
Product vibe (3 adjectives): Hangat, terstruktur, tegas (Warm, structured, crisp)
Brand identity: Cloraamine (terpusat di config/store.php — jangan hardcode)
Theme: Light only (dilarang mengaktifkan dark mode otomatis dari OS; @custom-variant dark dikunci ke .dark)

Color palette (Warm Cream & Deep Espresso — dari resources/css/tokens.css):
  - Canvas halaman (--bg-page): #FDF6EE
  - Permukaan kartu/panel/topbar/sidebar/tabel (--bg-surface): #FFFDF9
  - Permukaan inset & latar tombol sekunder (--bg-subtle / --btn-secondary-bg): #F6EBDD (hover #EADBC9)
  - Track/muted (--bg-muted): #EADBC9
  - Slot media/thumbnail produk (--bg-media): #EFE2D2
  - Permukaan gelap editorial hero & panel auth (--surface-ink): #33200F (elevated #452C17, border #5E3F24, text #FDF6EE)
  - Teks: primary #33200F, secondary #543921, muted #735337, disabled #A68B72
  - Border: hairline #E8D9C8, subtle #D8C3AC, strong #B5987B
  - Navigasi aktif sidebar/kategori/tab (--accent): Deep Espresso #33200F (teks #FDF6EE)
  - Tombol CTA utama & aksen brand (--brand — Opsi A): Karamel/Terakota #9A4D18 (hover #7C3C11, active #6E340E, teks #FFFDF9, soft #F9ECDD, border #E2BFA0)
  - Semantik status: sukses #065F46/#ECFDF5, peringatan #92400E/#FFFBEB, bahaya #991B1B/#FEF2F2, info #7C3C11/#F9ECDD

Typography:
  - Heading: "Plus Jakarta Sans", "Inter", sans-serif
  - Body: "Inter", sans-serif (15px base, >= 14px pada tabel padat)
  - Monospace (kode pesanan/VA/referensi/slug): "JetBrains Mono", monospace
  - Numerik: font-variant-numeric: tabular-nums untuk seluruh harga Rp, kuantitas, VA, dan metrik

Shape & Elevation:
  - Radius: xs 4px, sm 6px, md 8px, lg 10px, xl 12px, pill 9999px
  - Shadow hangat espresso rgba(51, 32, 15, ...): card, card-hover, topbar, dropdown, modal

Dials:
  - Admin Toko: D7 / E3 / M2
  - Storefront E-Commerce: D5 / E5 / M4

Language and locale:
  - Bahasa Indonesia 100%
  - Mata uang: Rp 1.250.000 (white-space: nowrap di sel tabel)
  - Waktu & tanggal: format Indonesia, penyimpanan UTC, tampilan/pelaporan WITA (Asia/Makassar)

Must have:
  - Pembedaan tegas tombol utama (#9A4D18), tombol sekunder (#F6EBDD), dan tombol aksi baris (32px)
  - 2 aktor saja (Pembeli & Admin Toko) dan 2 metode pemenuhan (Ambil di Toko & Kirim via Ekspedisi)
  - Layout admin .admin-shell sejajar penuh tanpa topbar duplikat
  - Responsif teruji di ~390px, 768px, 1280px serta aksesibilitas WCAG AA

Must avoid:
  - Preset biru/slate lama (#2563EB, #EEF2F6)
  - Belang zebra tabel pekat yang mengganggu keterbacaan
  - Link teks polos bergaris bawah di kolom Aksi tabel atau tombol reset filter
  - Elemen simulasi, guard/aktor Developer, React/shadcn, atau fitur baru di luar spec
```

## Appendix H — Code-Output Stack (`Cloraamine` Laravel Blade)

Seluruh kode UI di repo ini **wajib** mengikuti stack aktif berikut (menggantikan default React/TypeScript/shadcn di `DESIGN.md` v2):

- **Template & Framework:** **Laravel Blade** (`resources/views/layouts/app.blade.php`, `shop/`, `auth/`, `payments/`, `admin/`, `vendor/pagination/tailwind.blade.php`).
- **Styling:** **Tailwind CSS v4** + **CSS custom properties & komponen terpusat** di `resources/css/tokens.css` dan `resources/css/app.css`. Jangan menulis inline `style="..."` baru di Blade jika bisa memakai class terstruktur di `app.css`.
- **Asset Bundler & Library JS:** **Vite** (`npm.cmd run build`), **Chart.js** (`chart.js/auto`) untuk grafik analitik admin, **qrcode** untuk QR pembayaran, dan vanilla JS progresif di `resources/js/app.js`.
- **Ikon:** SVG inline stroke konsisten (gaya Lucide, `stroke-width="1.8"`–`"2"`); hindari karakter teks/emoji sebagai pengganti ikon UI.
- **Pengujian:** **Pest** (`php artisan test`) — setiap perubahan view/CSS harus menjaga seluruh rute, form, label/teks asersi tes, dan build manifest tetap hijau.
- **Alat non-target:** Figma Make, Google Stitch, v0, dan Lovable **tidak digunakan** sebagai target output kode di repo ini.
