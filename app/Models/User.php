<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser, HasTenants
{
    use HasFactory, Notifiable;

    /**
     * is_super_admin bisa diisi lewat form karena satu-satunya form yang
     * memakainya ada di panel platform (hanya super admin). Tidak ada registrasi publik.
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_super_admin',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_super_admin' => 'boolean',
        ];
    }

    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class)->withPivot('role')->withTimestamps();
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return match ($panel->getId()) {
            'platform' => (bool) $this->is_super_admin,
            'admin' => (bool) $this->is_super_admin || $this->tenants()->exists(),
            default => false,
        };
    }

    public function getTenants(Panel $panel): Collection
    {
        // Super admin boleh membuka semua tenant (untuk dukungan). Perlu dibatasi bila tenant sudah ribuan.
        return (bool) $this->is_super_admin ? Tenant::query()->get() : $this->tenants()->get();
    }

    public function canAccessTenant(Model $tenant): bool
    {
        return (bool) $this->is_super_admin
            || $this->tenants()->whereKey($tenant->getKey())->exists();
    }
}
