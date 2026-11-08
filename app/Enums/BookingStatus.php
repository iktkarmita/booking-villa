<?php

namespace App\Enums;

enum BookingStatus: string
{
    case PendingPayment = 'pending_payment';
    case Confirmed = 'confirmed';
    case CheckedIn = 'checked_in';
    case Completed = 'completed';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return __('site.status.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::PendingPayment => 'warning',
            self::Confirmed, self::CheckedIn => 'success',
            self::Completed => 'info',
            self::Expired, self::Cancelled => 'danger',
        };
    }

    /** Status yang masih menahan tanggal di kalender. */
    public function holdsDates(): bool
    {
        return in_array($this, [self::PendingPayment, self::Confirmed, self::CheckedIn], true);
    }
}
