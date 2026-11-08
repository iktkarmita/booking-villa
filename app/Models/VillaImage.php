<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VillaImage extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    public function villa(): BelongsTo
    {
        return $this->belongsTo(Villa::class);
    }
}
