<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Expire overdue menunggu_pembayaran orders every minute idempotently.
Schedule::command('store:expire-overdue-orders')->everyMinute();
