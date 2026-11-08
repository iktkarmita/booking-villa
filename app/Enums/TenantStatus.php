<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum TenantStatus: string implements HasLabel, HasColor
{
    case Trial = 'trial';
    case Active = 'active';
    case Suspended = 'suspended';

    public function getLabel(): string
    {
        return __('booking.tenant_status.'.$this->value);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Trial => 'warning',
            self::Active => 'success',
            self::Suspended => 'danger',
        };
    }
}
