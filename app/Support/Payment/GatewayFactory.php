<?php

namespace App\Support\Payment;

use App\Contracts\PaymentGateway;
use App\Models\TenantSetting;
use InvalidArgumentException;

final class GatewayFactory
{
    /** Gateway yang boleh dipilih owner. "fake" hanya di luar production. */
    public static function available(): array
    {
        return app()->environment('production') ? ['manual'] : ['manual', 'fake'];
    }

    /** @throws InvalidArgumentException */
    public static function make(string $name): PaymentGateway
    {
        if (! in_array($name, self::available(), true) || $name === 'manual') {
            throw new InvalidArgumentException("Gateway tidak tersedia: {$name}");
        }

        return match ($name) {
            'fake' => new FakeGateway,
        };
    }

    /** Gateway milik tenant, atau null bila tenant memakai transfer manual. */
    public static function forSettings(?TenantSetting $settings): ?PaymentGateway
    {
        $name = $settings?->gateway ?: 'manual';

        if ($name === 'manual' || ! in_array($name, self::available(), true)) {
            return null;
        }

        return self::make($name);
    }
}
