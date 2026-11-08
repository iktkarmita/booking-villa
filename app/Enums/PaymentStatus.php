<?php

namespace App\Enums;

/** Status pembayaran booking (terpisah dari status booking). */
enum PaymentStatus: string
{
    case Unpaid = 'unpaid';
    case Partial = 'partial';
    case Paid = 'paid';
    case Refunded = 'refunded';

    public function label(): string
    {
        return __('site.payment_status.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Unpaid => 'danger',
            self::Partial => 'warning',
            self::Paid => 'success',
            self::Refunded => 'gray',
        };
    }
}
