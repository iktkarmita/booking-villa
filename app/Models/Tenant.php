<?php

namespace App\Models;

use App\Enums\SubscriptionStatus;
use App\Enums\TenantStatus;
use Filament\Models\Contracts\HasName;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tenant extends Model implements HasName
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => TenantStatus::class,
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Tenant $tenant) {
            if (blank($tenant->subdomain) && filled($tenant->slug)) {
                $tenant->subdomain = $tenant->slug;
            }
        });

        // Setiap tenant baru otomatis punya baris pengaturan (nilai default dari database).
        static::created(fn (Tenant $tenant) => $tenant->settings()->create());
    }

    public function getFilamentName(): string
    {
        return (string) $this->name;
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('role')->withTimestamps();
    }

    public function settings(): HasOne
    {
        return $this->hasOne(TenantSetting::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function isSuspended(): bool
    {
        return $this->status === TenantStatus::Suspended;
    }

    /** Langganan yang sedang berlaku (trial/aktif dan belum lewat tanggal berakhir). */
    public function currentSubscription(): ?Subscription
    {
        return $this->subscriptions()
            ->whereIn('status', SubscriptionStatus::usable())
            ->where(fn ($query) => $query
                ->whereNull('ends_at')
                ->orWhere('ends_at', '>=', now()->toDateString()))
            ->latest('starts_at')
            ->first();
    }

    /** Batas total kamar aktif (semua villa) menurut paket saat ini. 0 jika tidak ada langganan berlaku. */
    public function roomLimit(): int
    {
        return (int) ($this->currentSubscription()?->plan?->max_rooms ?? 0);
    }
}
