<?php

namespace App\Support;

final class Money
{
    public static function idr(int|float|string|null $amount): string
    {
        return 'Rp '.number_format((float) $amount, 0, ',', '.');
    }
}
