# Spesifikasi UI Minimalist (Pengganti `docs/UI.md`)

> **Status:** Draf spesifikasi desain antarmuka baru untuk menggantikan gaya *Light Neumorphism + Motion* pada `iraweb`.  
> **Catatan Merek:** Nama merek toko masih dalam diskusi. Seluruh tampilan wajib membaca nama merek dari konfigurasi terpusat (`config('app.name')` / `config('store.name')`), dilambangkan `[Nama Toko]` di dokumen ini.

---

## 1. Prinsip Desain Minimalist

1. **Bersih, datar, dan terstruktur (Flat + Hairline).** Meninggalkan total latar lavender `#E9EBF8`, bayangan ganda neumorphism (`--shadow-xs..lg`), dan inset shadow pada input. Hierarki dibentuk oleh **ruang kosong yang terukur, tipografi yang tegas, serta garis batas `1px` yang halus**.
2. **Kontras tinggi dan mudah dibaca.** Latar kanvas putih/abu-abu sangat terang (`#FFFFFF` / `#FAFAFA`), teks utama gelap pekat (`#111827`), dan satu warna aksen netral/brand yang hemat pemakaiannya.
3. **Konten lebih utama daripada dekorasi.** Tidak ada banner gradien besar yang menguasai halaman, tidak ada kartu berbayangan tebal, dan tidak ada animasi scroll-reveal atau stagger yang memperlambat interaksi.
4. **Gerak hanya sebagai umpan balik halus.** Transisi cukup `120ms–180ms` untuk hover, focus, dropdown, dan modal; hormati `prefers-reduced-motion`.
5. **Bebas elemen simulasi.** Tidak ada `SimBanner`, tidak ada `SimBadge`, tidak ada panel HTTP simulasi atau popup rahasia 5 ketukan, tidak ada label `SIMULASI`/`SIMULATOR`.

---

## 2. Dua Permukaan + Alur Pembayaran

Karena role `developer` dan halaman simulator `/pay/sim/*` dihapus, aplikasi hanya memiliki **2 permukaan utama** ditambah halaman status pembayaran:

| Permukaan | Pengguna | Pola Navigasi | Karakter Visual |
| --- | --- | --- | --- |
| **Storefront** | Pengunjung & Pembeli | Header atas ringkas (Logo/Nama Toko, Katalog, Pencarian, Keranjang, Pesanan, Akun/Masuk); di ponsel menu responsif / bottom nav ≤ 5 item. | Kanvas putih bersih, grid produk rapi, whitespace lega, CTA hitam/slate atau aksen tunggal yang jelas. |
| **Admin Toko** | Admin Toko | Sidebar minimalis kiri (desktop) dengan pemisah tipis `1px` (Ringkasan/Laporan, Produk, Kategori, Pesanan, Pembayaran, Akun Pembeli); drawer di mobile. | Padat tapi lapang, tabel bersih dengan border horizontal tipis, filter sebaris di atas tabel. |
| **Pembayaran & Hasil** | Pembeli | Layout terfokus satu kolom (`max-w-xl` / `max-w-2xl`) tanpa navigasi yang mengalihkan perhatian. | Ringkasan tagihan, QR/VA/tombol bayar gateway riil, countdown jelas, halaman hasil berhasil/gagal/kedaluwarsa, dan struk cetak hitam-putih rapi. |

---

## 3. Sistem Token Minimalist (`resources/css/tokens.css` Target)

Nilai berikut menggantikan seluruh token lavender neumorphism di `iraweb`:

### 3.1 Palet Warna Minimalist
- **Latar & Permukaan:**
  - `--bg-page: #FFFFFF` (kanvas utama storefront)
  - `--bg-subtle: #F9FAFB` (latar area sekunder / sidebar admin / zebra ringan)
  - `--bg-surface: #FFFFFF` (kartu & panel)
  - `--bg-muted: #F3F4F6` (state hover lembut, badge netral, track)
- **Garis Batas:**
  - `--border-subtle: #E5E7EB` (`1px solid` pemisah kartu, tabel, input)
  - `--border-strong: #D1D5DB` (hover border pada input/tombol sekunder)
- **Teks:**
  - `--text-primary: #111827` (judul, teks utama, angka harga)
  - `--text-secondary: #4B5563` (deskripsi, label sekunder)
  - `--text-muted: #6B7280` (metadata, placeholder yang tetap lolos WCAG AA pada `#FFFFFF`)
- **Aksen Utama (Minimalist Monochrome / Slate — dapat disetel saat rebranding):**
  - `--accent: #111827` (atau satu warna brand tunggal setelah nama merek final ditetapkan)
  - `--accent-hover: #1F2937`
  - `--accent-foreground: #FFFFFF`
- **Semantik Status (selalu warna + teks/ikon, tidak warna saja):**
  - Sukses: teks `#065F46`, latar `#ECFDF5`, border `#A7F3D0`
  - Peringatan / Menunggu: teks `#92400E`, latar `#FFFBEB`, border `#FDE68A`
  - Gagal / Destruktif: teks `#991B1B`, latar `#FEF2F2`, border `#FECACA`
  - Info / Diproses: teks `#1E40AF`, latar `#EFF6FF`, border `#BFDBFE`

### 3.2 Tipografi, Radius, dan Elevasi
- **Font:** Satu keluarga sans-serif modern yang bersih (mis. `Inter` atau `Plus Jakarta Sans`) + `tabular-nums` (`font-variant-numeric: tabular-nums`) untuk seluruh harga `Rp`, kuantitas, dan nomor referensi/VA; `JetBrains Mono` / `ui-monospace` hanya untuk kode pesanan/VA/referensi.
- **Radius:** Tajam-terkendali (`6px` untuk input/badge/tombol, `8px–10px` untuk kartu & panel, `12px` untuk modal, `9999px` hanya untuk pill status kecil).
- **Elevasi / Shadow:** Hampir seluruhnya datar (`box-shadow: none` + `1px solid var(--border-subtle)`). Shadow tipis (`0 1px 2px rgba(0,0,0,0.05)` atau `0 10px 25px rgba(0,0,0,0.08)`) hanya untuk dropdown, toast, dan modal.

---

## 4. Aturan Komponen & Aksesibilitas

1. **Tombol:** Maksimal 3 varian (`primary`, `secondary` outline `1px`, `danger` outline/solid). Tinggi `40px–44px`, label tindakan spesifik berbahasa Indonesia (*"Simpan"*, *"Tambah ke Keranjang"*, *"Lanjut ke Pembayaran"*).
2. **Input & Form:** Label selalu terlihat di atas input (bukan placeholder sebagai label), border `1px solid`, focus ring jelas `2px`, error inline per field dengan `aria-describedby`.
3. **Kartu Produk:** Rasio konsisten (`1:1` atau `4:3`), mendukung gambar produk jika nantinya ditambahkan atau placeholder ikon/inisial netral yang tenang (tanpa gradien warna-warni mencolok), judul, harga `Rp`, dan info ketersediaan stok yang ringkas.
4. **Tabel Admin:** Header datar berlatar `--bg-subtle`, garis pembatas horizontal `1px`, padding nyaman, di mobile berubah menjadi daftar kartu ringkas tanpa horizontal scroll halaman.
5. **Struk / Cetak:** Desain hitam-putih bersih berbasis `@media print`, tanpa badge atau watermark simulasi.
6. **Responsif & A11y Baseline:** Tetap memeriksa 3 breakpoint (`~390px`, `768px`, `1280px`), target sentuh minimal `44×44px` di layar sentuh, kontras teks minimal `4.5:1` (WCAG AA), dan navigasi keyboard utuh.

---

## 5. Daftar Layar Minimalist Target

### Storefront (Pembeli & Tamu)
- Beranda (`/`) — hero teks ringkas + produk pilihan/terbaru (tanpa banner gradien tebal).
- Katalog & Pencarian (`/katalog`, `/produk/{id}`) — filter kategori + search bar bersih + grid produk minimalis.
- Auth (`/masuk`, `/daftar`) — kartu form terpusat sederhana; form `/masuk` tanpa tab *Developer* (hanya *Pembeli* dan *Admin Toko*, atau rute admin terpisah jika diputuskan demikian).
- Keranjang (`/keranjang`) & Checkout (`/checkout`) — layout 2 kolom (daftar item & metode/pengambilan di kiri, ringkasan tagihan sticky di kanan).
- Pembayaran (`/pesanan/{order}/bayar` atau redirect/Snap gateway riil) + Halaman Hasil (`/pembayaran/{payment}/hasil`) + Struk (`/pembayaran/{payment}/struk`).
- Riwayat Pesanan & Pembayaran (`/pesanan`, `/pesanan/{id}`, `/pembayaran`) & Profil/Alamat (`/profil`, jika dipertahankan).

### Admin Toko
- Ringkasan Toko / Dashboard (`/admin`) — kartu metrik bersih dan tabel pesanan terbaru / produk terlaris.
- Manajemen Kategori (`/admin/kategori`) & Produk + Stok (`/admin/produk`).
- Manajemen Pesanan (`/admin/pesanan`) & Pemantauan Pembayaran (`/admin/pembayaran`, read-only dari gateway).
- Manajemen Akun Pembeli (`/admin/akun-pembeli`).
