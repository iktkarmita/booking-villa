<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CalendarDay extends Model
{
    use BelongsToTenant;

    public const TYPE_BOOKING = 'booking';

    public const TYPE_BLOCK = 'block';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['date' => 'date:Y-m-d'];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }
}
