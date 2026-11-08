<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Refund extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected $hidden = ['dest_account_number'];

    protected function casts(): array
    {
        return [
            'suggested_amount' => 'integer',
            'amount_approved' => 'integer',
            'dest_account_number' => 'encrypted',
            'decided_at' => 'datetime',
            'refunded_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
