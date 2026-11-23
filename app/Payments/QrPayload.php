<?php

namespace App\Payments;

// Payload QR untuk instruksi pembayaran ('url' untuk URL pembayaran gateway, 'raw' untuk string QRIS mentah).
final class QrPayload
{
    public function __construct(
        public string $type,
        public string $value,
    ) {}
}
