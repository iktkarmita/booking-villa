<?php

namespace App\Support;

use App\Models\Room;
use App\Models\Tenant;

/** Kuota kamar aktif per tenant (total di semua villa) menurut paket langganan. */
final class RoomQuota
{
    public static function used(Tenant $tenant): int
    {
        return Room::withoutTenancy()
            ->where('tenant_id', $tenant->getKey())
            ->where('is_active', true)
            ->count();
    }

    public static function limit(Tenant $tenant): int
    {
        return $tenant->roomLimit();
    }

    public static function remaining(Tenant $tenant): int
    {
        return max(0, self::limit($tenant) - self::used($tenant));
    }

    /** Apakah kamar ini boleh berstatus aktif? Kamar yang sudah aktif selalu boleh tetap aktif. */
    public static function canActivate(Tenant $tenant, ?Room $room = null): bool
    {
        if ($room !== null && $room->exists && $room->getOriginal('is_active')) {
            return true;
        }

        return self::remaining($tenant) > 0;
    }
}
