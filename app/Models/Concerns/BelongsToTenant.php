<?php

namespace App\Models\Concerns;

use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use App\Support\CurrentTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Pasang pada setiap model yang punya kolom tenant_id.
 *
 * - Query otomatis dibatasi ke tenant aktif (lihat CurrentTenant).
 * - tenant_id otomatis terisi saat membuat data.
 * - Menulis data untuk tenant lain dari konteks tenant aktif ditolak.
 * - Gunakan Model::withoutTenancy() HANYA untuk kebutuhan lintas tenant (mis. panel platform).
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope());

        static::creating(function (Model $model) {
            $current = app(CurrentTenant::class);

            if (! $current->has()) {
                return;
            }

            $tenantId = $model->getAttribute('tenant_id');

            if ($tenantId === null) {
                $model->setAttribute('tenant_id', $current->id());
            } elseif ((int) $tenantId !== (int) $current->id()) {
                throw new LogicException('Menulis data tenant lain dari konteks tenant aktif ditolak.');
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public static function withoutTenancy(): Builder
    {
        return static::withoutGlobalScope(TenantScope::class);
    }
}
