<?php

namespace App\Payments;

use LogicException;

// Registry provider & driver pembayaran yang didukung aplikasi.
// Driver kosong atau tidak dikenal akan gagal sejak application boot (fail fast).
class ProviderRegistry
{
    /** @var array<string, class-string<PaymentAdapter>> */
    public const DRIVERS = [
        'manual' => ManualPaymentAdapter::class,
    ];

    /** @var array<string, class-string<PaymentAdapter>> */
    public const PROVIDERS = [
        'manual' => ManualPaymentAdapter::class,
    ];

    public static function isKnownProvider(string $provider): bool
    {
        return array_key_exists($provider, self::PROVIDERS);
    }

    public static function validateDriver(string $driver): string
    {
        if ($driver === '' || ! array_key_exists($driver, self::DRIVERS)) {
            throw new LogicException(
                'PAYMENT_DRIVER tidak dikenal atau kosong: "'.$driver.'". '.
                'Nilai yang didukung: '.implode(', ', array_keys(self::DRIVERS)).'. Periksa .env PAYMENT_DRIVER.'
            );
        }

        return $driver;
    }

    /** @return class-string<PaymentAdapter> */
    public static function adapterClassFor(string $driver): string
    {
        self::validateDriver($driver);

        return self::DRIVERS[$driver];
    }

    /** @return class-string<PaymentAdapter> */
    public static function adapterClassForProvider(string $provider): string
    {
        if (! self::isKnownProvider($provider)) {
            throw new LogicException('Provider webhook tidak dikenal: "'.$provider.'".');
        }

        return self::PROVIDERS[$provider];
    }
}
