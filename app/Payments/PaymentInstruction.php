<?php

namespace App\Payments;

// Struktur instruksi pembayaran netral yang dihasilkan oleh PaymentAdapter.
final class PaymentInstruction
{
    public function __construct(
        public string $paymentId,
        public string $referenceCode,
        public ?QrPayload $qr,
        public ?string $vaNumber,
        public ?string $paymentToken,
        public \DateTimeImmutable $expiresAt,
    ) {}
}
