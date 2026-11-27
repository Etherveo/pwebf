# Spesifikasi UI Minimalist Warm Cream & Deep Espresso (Pengganti `docs/UI.md`)

> **Status:** Spesifikasi desain antarmuka aktif untuk proyek e-commerce **Cloraamine**.  
> **Catatan Merek:** Seluruh tampilan wajib membaca nama merek dan copy dari konfigurasi terpusat (`config('store.name')`, `config('store.*')`), bukan string toko yang di-hardcode.

---

## 1. Prinsip Desain (Kontras 3-Lapis + Hierarki Tombol Tegas)

1. **Hierarki permukaan 3-lapis yang tegas.** Pisahkan dengan jelas antara **Kanvas Halaman** (`--bg-page: #FDF6EE`), **Permukaan Kartu/Panel** (`--bg-surface: #FFFDF9`), dan **Area Inset/Sekunder** (`--bg-subtle: #F6EBDD` / `--bg-media: #EFE2D2`).
2. **Hierarki tombol & aksi yang tidak membingungkan.**
   - **Tombol Utama (`.btn-primary`)** memakai warna brand karamel/terakota (`--brand: #9A4D18`, hover `--brand-hover: #7C3C11`, teks `#FFFDF9`) sehingga langsung kontras dan terpisah dari menu aktif sidebar (`--accent: #33200F` Deep Espresso) maupun teks biasa.
   - **Tombol Sekunder (`.btn-secondary`, `.btn-ghost-sm`, `.admin-action-link`)** memakai latar terisi hangat (`--btn-secondary-bg: #F6EBDD`, hover `#EADBC9`) berborder tegas agar tidak menyatu ke kartu putih `#FFFDF9`. Semua aksi baris tabel (termasuk tautan `Ubah`, `Atur stok`, `Riwayat stok`, `Detail`) wajib tampil sebagai tombol kompak seragam (`min-height: 32px`), bukan teks garis bawah biasa.
   - **Tombol Destruktif (`.btn-danger-ghost`)** memakai latar lembut `--status-danger-bg` (`#FEF2F2`) dan border `--status-danger-border` (`#FCA5A5`) secara default.
3. **Batas antar-section & layout sejajar.** Gunakan border `--border-subtle: #D8C3AC`, `--border-strong: #B5987B`, garis pemisah internal `--border-hairline: #E8D9C8`, elevasi mikro (`--shadow-card`), serta penanda aksen kiri `4px solid var(--brand)` pada `.admin-head` dan `.home-section-head`.
4. **Gerak hanya sebagai umpan balik halus.** Transisi cukup `150ms` untuk hover, focus, dropdown, dan modal; hormati `prefers-reduced-motion`.
5. **Bebas elemen simulasi.** Tidak ada `SimBanner`, tidak ada `SimBadge`, tidak ada panel HTTP simulasi atau popup rahasia, tidak ada label `SIMULASI`/`SIMULATOR`.

---

## 2. Dua Permukaan + Alur Pembayaran

Aplikasi melayani **2 aktor** (`pembeli` dan `admin_toko`) dengan **2 permukaan utama** ditambah alur pembayaran:

| Permukaan | Pengguna | Pola Navigasi | Karakter Visual |
| --- | --- | --- | --- |
| **Storefront** | Pengunjung & Pembeli | Topbar sticky full-width (`#FFFDF9`, border bawah `#D8C3AC`, kontainer dalam `max-width: 72rem`, CTA `Daftar Pembeli` memakai `.topbar-cta` `#9A4D18`, `Masuk`/`Keluar` memakai tombol sekunder) + drawer ponsel; footer full-width (`#FFFDF9`). | Kanvas `#FDF6EE`, hero editorial gelap solid (`#33200F`), section beranda berbatas top-divider & `.home-section-head` beraksen kiri `4px solid --brand`, sidebar kategori katalog sticky di desktop, kartu produk `#FFFDF9` dengan frame media `#EFE2D2`. |
| **Admin Toko** | Admin Toko | CSS Grid `.admin-shell` (`16rem minmax(0, 1fr)` di desktop `>= 1024px`) dengan sidebar kiri sticky penuh (`height: 100vh`, `#FFFDF9`, border kanan `#D8C3AC`, item aktif `#33200F` beraksen kiri `#9A4D18`) dan kolom kanan `.admin-workspace` (tanpa topbar duplikat); drawer di mobile `< 1024px`. | Kanvas `#FDF6EE`, `.admin-page` (`max-width: 84rem`) sejajar penuh dengan footer, header halaman `.admin-head` terbingkai `#FFFDF9` beraksen kiri `#9A4D18`, tabel bersih berbaris `#FFFDF9` dengan border `#E8D9C8` dan hover `#F9ECDD` (tanpa zebra pekat). |
| **Pembayaran & Hasil** | Pembeli | Layout 2 kolom (instruksi + unggah bukti di kiri, ringkasan & countdown sticky di kanan) atau 1 kolom terfokus untuk halaman hasil & struk. | Kartu `#FFFDF9` di atas kanvas `#FDF6EE`, countdown beraksen kiri `#9A4D18`, struk cetak hitam-putih bersih berbasis `@media print`. |

---

## 3. Sistem Token (`resources/css/tokens.css`)

### 3.1 Palet Warna & Permukaan
- **Latar & Permukaan 3-Lapis:**
  - `--bg-page: #FDF6EE` (kanvas utama storefront & ruang kerja admin)
  - `--bg-surface: #FFFDF9` (kartu, panel, modal, topbar, sidebar, footer, baris tabel)
  - `--bg-subtle: #F6EBDD` (inset dalam kartu, header tabel, box statistik)
  - `--bg-muted: #EADBC9` (track progress bar, badge netral)
  - `--bg-media: #EFE2D2` (latar slot gambar/ikon produk)
- **Permukaan Gelap Editorial:**
  - `--surface-ink: #33200F`, `--surface-ink-elevated: #452C17`, `--surface-ink-border: #5E3F24`, `--surface-ink-text: #FDF6EE`, `--surface-ink-muted: #E2CEB8`
- **Garis Batas:**
  - `--border-hairline: #E8D9C8` (pembatas baris tabel, KV list, dan pemisah dalam kartu)
  - `--border-subtle: #D8C3AC` (batas utama kartu, input, section, topbar/footer)
  - `--border-strong: #B5987B` (border tombol sekunder, hover input, empty-state dashed border)
- **Teks (WCAG AA+):**
  - `--text-primary: #33200F` (judul, teks utama, angka harga)
  - `--text-secondary: #543921` (deskripsi, label sekunder, isi tabel)
  - `--text-muted: #735337` (metadata, heading kelompok, placeholder terbaca jelas)
- **Aksen & Brand:**
  - `--accent: #33200F` (`--accent-hover: #4A3018`, `--accent-active: #221409`, `--accent-foreground: #FDF6EE`) — untuk navigasi aktif sidebar/kategori dan kartu highlight.
  - `--brand: #9A4D18` (`--brand-hover: #7C3C11`, `--brand-soft: #F9ECDD`, `--brand-border: #E2BFA0`, `--brand-ink: #6E340E`, `--brand-on-dark: #F2C18D`) — untuk tombol utama `.btn-primary`, penanda aksen kiri section/header, fokus ring, dan tautan aktif.
  - `--btn-secondary-bg: #F6EBDD` (`--btn-secondary-hover: #EADBC9`) — untuk tombol sekunder dan tombol aksi baris tabel.
- **Semantik Status (selalu warna + teks/ikon, tidak warna saja):**
  - Sukses: teks `#065F46`, latar `#ECFDF5`, border `#6EE7B7`
  - Peringatan / Menunggu: teks `#92400E`, latar `#FFFBEB`, border `#FCD34D`
  - Gagal / Destruktif: teks `#991B1B`, latar `#FEF2F2`, border `#FCA5A5`
  - Info / Diproses: teks `#7C3C11`, latar `#F9ECDD`, border `#E2BFA0`

### 3.2 Tipografi, Radius, dan Elevasi Mikro
- **Font:** `Plus Jakarta Sans` untuk heading, `Inter` untuk body + `tabular-nums` (`font-variant-numeric: tabular-nums`) untuk seluruh harga `Rp`, kuantitas, dan nomor referensi/VA; `JetBrains Mono` untuk kode pesanan/VA/referensi.
- **Radius:** Tajam-terkendali (`4px` badge kode, `6px` input/tombol, `8px–10px` kartu & panel, `12px` modal/hero, `9999px` pill status).
- **Elevasi / Shadow:** Elevasi mikro hangat (`rgba(51, 32, 15, ...)` untuk `--shadow-card`, `--shadow-topbar`, `--shadow-card-hover`, `--shadow-dropdown`, `--shadow-modal`).

---

## 4. Aturan Komponen, Section & Aksesibilitas

1. **Header Halaman & Section:**
   - Gunakan `.admin-head` (kartu `#FFFDF9` berborder `--border-subtle` + `border-left: 4px solid var(--brand)`) untuk judul halaman Admin maupun alur pembeli.
   - Pada beranda, pisahkan tiap bagian produk dengan `.home-section` (garis batas atas + `.home-section-head` berbingkai `#FFFDF9` dan beraksen kiri `--brand`).
   - Di dalam `.card`, pisahkan header kartu (`.analytics-card-head`) dan bar filter (`.admin-filters`) dari isi data dengan garis batas `--border-hairline`.
2. **Tombol & Aksi:** Gunakan `.btn-primary` (`#9A4D18`), `.btn-secondary` / `.btn-ghost-sm` (`#F6EBDD`), `.btn-danger-ghost` (`#FEF2F2`), `.admin-action-link` (tombol aksi baris kompak `32px`), serta `.btn-hero` khusus di atas `.home-hero` gelap. Hindari tautan teks polos untuk aksi primer/tabel.
3. **Input & Form:** Label terlihat di atas input, border `1px solid var(--border-subtle)`, focus ring karamel `3px rgba(154, 77, 24, 0.2)`, error inline per field dengan `aria-describedby`.
4. **Kartu Produk:** Rasio `4:3` di grid dan `1:1` di detail produk, menampilkan foto produk jika tersedia (`hasImage()`) atau tile ikon netral di atas `--bg-media`, pemisah harga tipis, dan status ketersediaan stok.
5. **Tabel & Pagination:** Header tabel `#F6EBDD` berborder `#D8C3AC`, baris bersih `#FFFDF9` dengan border `#E8D9C8` dan hover `#F9ECDD` (tanpa belang zebra pekat), `white-space: nowrap` pada `.admin-cell-price`, aksi baris dikelompokkan rapi (`.admin-row-actions` / `.admin-row-actions-grid`), dan di mobile (`< 768px`) berubah menjadi daftar kartu ringkas berlabel (`data-label`) tanpa scroll horizontal halaman. Pagination (`resources/views/vendor/pagination/tailwind.blade.php`) memakai kelas `.pager*` (`@custom-variant dark` dikunci ke class `.dark`).
6. **Konsistensi Lintas Percakapan:** Setiap perubahan atau penambahan halaman UI baru di workspace ini wajib menggunakan kelas dan token dari `resources/css/tokens.css` & `resources/css/app.css` agar konsisten dengan gaya desain aktif di atas.
