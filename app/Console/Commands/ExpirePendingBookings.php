<?php

namespace App\Console\Commands;

use App\Support\BookingService;
use App\Support\PaymentService;
use Illuminate\Console\Command;

class ExpirePendingBookings extends Command
{
    protected $signature = 'bookings:expire';

    protected $description = 'Kedaluwarsakan booking yang belum dibayar dan bebaskan tanggalnya';

    public function handle(): int
    {
        $count = BookingService::expireOverdue();

        $payments = PaymentService::expireStalePayments();

        $this->info("{$count} booking dan {$payments} tagihan pelunasan dikedaluwarsakan.");

        return self::SUCCESS;
    }
}
