<?php

// Katalog metode pembayaran terpusat.
// Driver adapter diatur melalui PAYMENT_DRIVER dan webhook melalui PAYMENT_WEBHOOK_SECRET.
return [
    'driver' => env('PAYMENT_DRIVER', 'manual'),

    'webhook_secret' => env('PAYMENT_WEBHOOK_SECRET', ''),

    'poll_seconds' => 3,

    'methods' => [
        'qris' => [
            'code' => 'qris',
            'label' => 'QRIS',
            'kind' => 'qr',
            'bank_code' => null,
            'active' => true,
        ],
        'dana' => [
            'code' => 'dana',
            'label' => 'Dana',
            'kind' => 'qr',
            'bank_code' => null,
            'active' => true,
        ],
        'gopay' => [
            'code' => 'gopay',
            'label' => 'GoPay',
            'kind' => 'qr',
            'bank_code' => null,
            'active' => true,
        ],
        'va_bri' => [
            'code' => 'va_bri',
            'label' => 'VA BRI',
            'kind' => 'va',
            'bank_code' => '01',
            'active' => true,
        ],
        'va_bca' => [
            'code' => 'va_bca',
            'label' => 'VA BCA',
            'kind' => 'va',
            'bank_code' => '02',
            'active' => true,
        ],
        'va_bni' => [
            'code' => 'va_bni',
            'label' => 'VA BNI',
            'kind' => 'va',
            'bank_code' => '03',
            'active' => true,
        ],
    ],
];
