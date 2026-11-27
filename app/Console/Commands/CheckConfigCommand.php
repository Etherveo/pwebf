<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

// Validasi konfigurasi inti toko (koneksi database, APP_KEY, timezone, dan payment driver).
class CheckConfigCommand extends Command
{
    protected $signature = 'store:check-config';

    protected $description = 'Validate store configuration (database, keys, timezones, payment driver)';

    public function handle(): int
    {
        $failures = [];
        $driver = (string) config('database.default');

        if (! in_array($driver, ['mysql', 'mariadb', 'sqlite'], true)) {
            $failures[] = 'DB_CONNECTION harus mysql, mariadb, atau sqlite, saat ini: '.$driver;
        }

        try {
            DB::connection()->getPdo();
        } catch (\Throwable $e) {
            $failures[] = 'Koneksi database gagal: '.$e->getMessage();
        }

        if ((string) config('app.key') === '') {
            $failures[] = 'APP_KEY kosong. Jalankan: php artisan key:generate';
        }

        if ((string) config('store.reporting_timezone') !== 'Asia/Makassar') {
            $failures[] = 'REPORTING_TIMEZONE harus Asia/Makassar.';
        }

        // Storage timezone must stay UTC; reporting is WITA.
        if ((string) config('app.timezone') !== 'UTC') {
            $failures[] = 'app.timezone harus UTC untuk penyimpanan; pelaporan memakai REPORTING_TIMEZONE.';
        }

        if ((string) config('payment.driver') === '') {
            $failures[] = 'PAYMENT_DRIVER tidak boleh kosong.';
        }

        foreach ($failures as $failure) {
            $this->error($failure);
        }

        if ($failures !== []) {
            return self::FAILURE;
        }

        $this->info('Store config OK: '.strtoupper($driver).', APP_KEY, UTC storage, WITA reporting, payment driver.');

        return self::SUCCESS;
    }
}
