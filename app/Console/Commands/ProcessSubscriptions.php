<?php

namespace App\Console\Commands;

use App\Support\SubscriptionService;
use Illuminate\Console\Command;

class ProcessSubscriptions extends Command
{
    protected $signature = 'subscriptions:process';

    protected $description = 'Proses langganan: kedaluwarsa, buat tagihan, tangguhkan yang menunggak, kirim pengingat';

    public function handle(): int
    {
        $r = SubscriptionService::process();
        $this->info("Kedaluwarsa: {$r['expired']}, tagihan baru: {$r['invoiced']}, ditangguhkan: {$r['suspended']}, pengingat: {$r['reminded']}");

        return self::SUCCESS;
    }
}
