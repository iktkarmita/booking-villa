<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum SubscriptionStatus: string implements HasLabel, HasColor
{
    case Trial = 'trial';
    case Active = 'active';
    case PastDue = 'past_due';
    case Canceled = 'canceled';

    public function getLabel(): string
    {
        return __('booking.subscription_status.'.$this->value);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Trial => 'warning',
            self::Active => 'success',
            self::PastDue => 'danger',
            self::Canceled => 'gray',
        };
    }

    /** Status yang masih boleh memakai layanan (kuota kamar berlaku). */
    public static function usable(): array
    {
        return [self::Trial->value, self::Active->value];
    }
}
