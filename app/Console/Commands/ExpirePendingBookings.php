<?php

namespace App\Console\Commands;

use App\Support\BookingService;
use Illuminate\Console\Command;

class ExpirePendingBookings extends Command
{
    protected $signature = 'bookings:expire';

    protected $description = 'Kedaluwarsakan booking yang belum dibayar dan bebaskan tanggalnya';

    public function handle(): int
    {
        $count = BookingService::expireOverdue();

        $this->info("{$count} booking dikedaluwarsakan.");

        return self::SUCCESS;
    }
}
