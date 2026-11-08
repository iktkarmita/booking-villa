<?php

namespace App\Models;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Booking extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'check_in' => 'date:Y-m-d',
            'check_out' => 'date:Y-m-d',
            'balance_due_date' => 'date:Y-m-d',
            'status' => BookingStatus::class,
            'payment_status' => PaymentStatus::class,
            'total' => 'integer',
            'dp_amount' => 'integer',
            'paid_amount' => 'integer',
            'nights' => 'integer',
            'guests_count' => 'integer',
            'expires_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class)->withTrashed();
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function remaining(): int
    {
        return max(0, $this->total - $this->paid_amount);
    }

    /** Pembayaran yang sedang ditunggu (terbaru yang masih pending). */
    public function pendingPayment(): ?Payment
    {
        return $this->payments()->where('status', 'pending')->latest('id')->first();
    }
}
