<?php

namespace App\Models;

use App\Models\Concerns\HasLocalizedText;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Amenity extends Model
{
    use HasLocalizedText;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['name' => 'array'];
    }

    public function villas(): BelongsToMany
    {
        return $this->belongsToMany(Villa::class);
    }
}
