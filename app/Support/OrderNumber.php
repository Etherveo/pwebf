<?php

namespace App\Support;

use App\Models\Order;

// Generator nomor pesanan unik & non-sekuensial menggunakan awalan config('store.order_prefix').
class OrderNumber
{
    private const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    public static function generate(): string
    {
        $prefix = strtoupper(trim((string) config('store.order_prefix', 'JBR')));
        if ($prefix === '') {
            $prefix = 'JBR';
        }

        do {
            $code = '';
            for ($i = 0; $i < 6; $i++) {
                $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
            }
            $number = $prefix.'-'.now()->format('ymd').'-'.$code;
        } while (Order::query()->where('order_number', $number)->exists());

        return $number;
    }
}
