<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasLocalizedText;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Villa extends Model
{
    use BelongsToTenant, HasFactory, HasLocalizedText, SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'name' => 'array',
            'description' => 'array',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Dipasang setelah trait BelongsToTenant, jadi tenant_id sudah terisi saat dijalankan.
        static::creating(function (Villa $villa) {
            if (blank($villa->slug)) {
                $villa->slug = static::uniqueSlug($villa);
            }
        });
    }

    private static function uniqueSlug(Villa $villa): string
    {
        $base = Str::slug((string) $villa->localized('name')) ?: 'villa';
        $slug = $base;
        $i = 2;

        while (static::withoutTenancy()->withTrashed()
            ->where('tenant_id', $villa->tenant_id)
            ->where('slug', $slug)
            ->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(VillaImage::class)->orderBy('sort_order');
    }

    public function amenities(): BelongsToMany
    {
        return $this->belongsToMany(Amenity::class);
    }
}
