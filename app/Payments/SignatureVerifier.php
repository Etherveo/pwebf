<?php

namespace App\Payments;

// Verifikasi HMAC SHA-256 webhook gateway (`hmac-sha256=<hex>`) atas raw request body.
// Secret kosong selalu gagal (fail closed).
class SignatureVerifier
{
    public const HEADER = 'X-Webhook-Signature';

    public const PREFIX = 'hmac-sha256=';

    public static function sign(string $rawBody, string $secret): string
    {
        return self::PREFIX.hash_hmac('sha256', $rawBody, $secret);
    }

    public static function verify(string $rawBody, ?string $signature, string $secret): bool
    {
        if ($secret === '' || $signature === null || $signature === '') {
            return false;
        }

        return hash_equals(self::sign($rawBody, $secret), $signature);
    }
}
