<?php

namespace App\Console\Commands;

use App\Services\OrderExpiryService;
use Illuminate\Console\Command;

// Scheduled job every minute from routes/console.php to expire overdue orders idempotently.
class ExpireOverdueOrdersCommand extends Command
{
    protected $signature = 'store:expire-overdue-orders';

    protected $description = 'Expire overdue menunggu_pembayaran orders and release reserved stock';

    public function handle(OrderExpiryService $expiry): int
    {
        $count = $expiry->expireOverdue();

        $this->info('Pesanan kedaluwarsa diproses: '.$count.'.');

        return self::SUCCESS;
    }
}
