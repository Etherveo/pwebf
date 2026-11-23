<?php

$storeName = env('STORE_NAME') ?: env('APP_NAME', 'Jubarol');

return [
    // Sumber kebenaran tunggal untuk nama merek di seluruh UI.
    // Ubah STORE_NAME / APP_NAME di .env atau ubah fallback 'Jubarol' di sini.
    'name' => $storeName,

    // Inisial/monogram singkat untuk badge logo di header dan sidebar.
    'mark' => env('STORE_MARK') ?: mb_strtoupper(mb_substr($storeName, 0, 1)),

    // Lokasi toko opsional; kosongkan untuk tidak menampilkan nama kota di UI.
    'location' => env('STORE_LOCATION', ''),

    // Awalan nomor pesanan (contoh: JBR-261124-ABC123) dan zona waktu pelaporan WITA.
    'order_prefix' => env('STORE_ORDER_PREFIX', 'JBR'),
    'reporting_timezone' => env('REPORTING_TIMEZONE', 'Asia/Makassar'),
    'payment_expiry_minutes' => (int) env('PAYMENT_EXPIRY_MINUTES', 30),

    // Label zona waktu pelaporan/tampilan.
    'timezone_label' => env('STORE_TIMEZONE_LABEL', 'WITA'),

    // Copy utama UI yang netral dan terpusat.
    'tagline' => env('STORE_TAGLINE', 'Belanja rapi dan terarah dalam satu tempat.'),
    'hero_headline' => env('STORE_HERO_HEADLINE', 'Kebutuhan harian tersaji ringkas dan jelas.'),
    'hero_description' => env('STORE_HERO_DESCRIPTION', 'Jelajahi katalog produk pilihan, pantau ketersediaan stok langsung, dan selesaikan pesanan dengan cepat.'),
    'auth_headline' => env('STORE_AUTH_HEADLINE', 'Mulai belanja dengan mudah.'),
    'auth_description' => env('STORE_AUTH_DESCRIPTION', 'Kelola keranjang, pantau status pesanan, dan lihat riwayat transaksi dalam satu akun.'),
    'catalog_headline' => env('STORE_CATALOG_HEADLINE', 'Temukan produk yang kamu butuhkan.'),
    'catalog_description' => env('STORE_CATALOG_DESCRIPTION', 'Telusuri pilihan produk aktif dan periksa ketersediaan stok terbaru.'),
];
