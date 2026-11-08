<?php

namespace App\Models;

use App\Exceptions\RoomQuotaExceeded;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasLocalizedText;
use App\Support\CurrentTenant;
use App\Support\RoomQuota;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Room extends Model
{
    use BelongsToTenant, HasFactory, HasLocalizedText, SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'name' => 'array',
            'base_price' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Pengaman terakhir: kamar aktif tidak boleh melebihi kuota paket (form juga memvalidasi ini).
        static::saving(function (Room $room) {
            $activating = $room->is_active && (! $room->exists || $room->isDirty('is_active'));

            if (! $activating) {
                return;
            }

            $tenantId = $room->tenant_id ?? app(CurrentTenant::class)->id();
            $tenant = $tenantId ? Tenant::find($tenantId) : null;

            if ($tenant === null) {
                return;
            }

            if (! RoomQuota::canActivate($tenant, $room)) {
                throw new RoomQuotaExceeded(__('villa.quota.exceeded', ['limit' => RoomQuota::limit($tenant)]));
            }
        });
    }

    public function villa(): BelongsTo
    {
        return $this->belongsTo(Villa::class);
    }
}
