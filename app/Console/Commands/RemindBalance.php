<?php

namespace App\Console\Commands;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Support\Notifier;
use Illuminate\Console\Command;

class RemindBalance extends Command
{
    protected $signature = 'bookings:remind-balance {--days=2 : Ingatkan bila jatuh tempo dalam sekian hari}';

    protected $description = 'Kirim email pengingat pelunasan DP yang segera/sudah jatuh tempo (sekali per booking)';

    public function handle(): int
    {
        $count = 0;

        Booking::withoutTenancy()
            ->where('status', BookingStatus::Confirmed->value)
            ->where('payment_status', PaymentStatus::Partial->value)
            ->whereNull('balance_reminded_at')
            ->whereNotNull('balance_due_date')
            ->whereDate('balance_due_date', '<=', now()->addDays((int) $this->option('days'))->toDateString())
            ->each(function (Booking $booking) use (&$count) {
                Notifier::balanceReminder($booking);
                $booking->update(['balance_reminded_at' => now()]);
                $count++;
            });

        $this->info("{$count} pengingat pelunasan dikirim.");

        return self::SUCCESS;
    }
}
