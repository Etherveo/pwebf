<?php

$storeName = env('STORE_NAME') ?: env('APP_NAME', 'Cloraamine');

return [
    // Sumber kebenaran tunggal untuk nama merek di seluruh UI.
    // Ubah STORE_NAME / APP_NAME di .env atau ubah fallback 'Cloraamine' di sini.
    'name' => $storeName,

    // Inisial/monogram singkat untuk badge logo di header dan sidebar.
    'mark' => env('STORE_MARK') ?: mb_strtoupper(mb_substr($storeName, 0, 1)),

    // Lokasi toko opsional; kosongkan untuk tidak menampilkan nama kota di UI.
    'location' => env('STORE_LOCATION', ''),

    // Awalan nomor pesanan (contoh: CL-261125-ABC123) dan zona waktu pelaporan WITA.
    'order_prefix' => env('STORE_ORDER_PREFIX', 'CL'),
    'reporting_timezone' => env('REPORTING_TIMEZONE', 'Asia/Makassar'),
    'payment_expiry_minutes' => (int) env('PAYMENT_EXPIRY_MINUTES', 30),

    // Label zona waktu pelaporan/tampilan.
    'timezone_label' => env('STORE_TIMEZONE_LABEL', 'WITA'),

    // Jendela deduplikasi event tampilan produk (dalam menit per pembeli & produk).
    'view_event_dedup_minutes' => (int) env('VIEW_EVENT_DEDUP_MINUTES', 30),

    // Kontak WhatsApp penjual & media sosial resmi toko.
    'whatsapp' => env('STORE_WHATSAPP', '081234567890'),
    'socials' => [
        [
            'platform' => 'Instagram',
            'handle' => '@cloraamine',
            'url' => 'https://www.instagram.com/cloraamine',
        ],
        [
            'platform' => 'TikTok',
            'handle' => '@cloraamine',
            'url' => 'https://www.tiktok.com/@cloraamine',
        ],
    ],

    // Daftar rekening transfer manual untuk halaman instruksi pembayaran.
    'bank_accounts' => [
        [
            'bank' => env('STORE_BANK_NAME', 'BRI'),
            'number' => env('STORE_BANK_NUMBER', '1234567890'),
            'holder' => env('STORE_BANK_HOLDER', $storeName),
        ],
    ],

    // Pilihan kurir ekspedisi beserta tarif ongkos kirim tetap (Rupiah).
    'couriers' => [
        'jne' => [
            'code' => 'jne',
            'name' => 'JNE REG',
            'cost_idr' => 12000,
        ],
        'jnt' => [
            'code' => 'jnt',
            'name' => 'J&T REG',
            'cost_idr' => 11000,
        ],
        'sicepat' => [
            'code' => 'sicepat',
            'name' => 'SiCepat REG',
            'cost_idr' => 10000,
        ],
    ],

    // Copy utama UI yang netral dan terpusat.
    'tagline' => env('STORE_TAGLINE', 'Photocard, album, merchandise, dan cetak stiker & pin pilihan.'),
    'hero_headline' => env('STORE_HERO_HEADLINE', 'Koleksi merchandise dan cetak custom tersaji rapi dalam satu tempat.'),
    'hero_description' => env('STORE_HERO_DESCRIPTION', 'Jelajahi katalog photocard, album, merchandise, serta paket stiker dan pin custom dengan stok transparan dan pemesanan praktis.'),
    'auth_headline' => env('STORE_AUTH_HEADLINE', 'Mulai belanja dengan mudah.'),
    'auth_description' => env('STORE_AUTH_DESCRIPTION', 'Kelola keranjang, pilih pengiriman ekspedisi atau ambil di tempat, dan pantau status pesanan dalam satu akun.'),
    'catalog_headline' => env('STORE_CATALOG_HEADLINE', 'Temukan koleksi dan produk pilihan.'),
    'catalog_description' => env('STORE_CATALOG_DESCRIPTION', 'Saring berdasarkan kategori, kondisi barang, status pre-order, dan ketersediaan stok terbaru.'),
];
