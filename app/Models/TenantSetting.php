<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantSetting extends Model
{
    protected $guarded = [];

    /** Tenant baru mendapat tingkat refund bawaan: H-7 100%, H-3 50%. */
    protected $attributes = [
        'refund_tiers' => '[{"days":7,"percent":100},{"days":3,"percent":50}]',
    ];

    protected $hidden = ['gateway_credentials', 'wa_credentials'];

    protected function casts(): array
    {
        return [
            // Kredensial disimpan terenkripsi (butuh APP_KEY).
            'gateway_credentials' => 'encrypted:array',
            'wa_credentials' => 'encrypted:array',
            'cancellation_policy' => 'array',
            'refund_tiers' => 'array',
            'dp_percent' => 'integer',
            'balance_due_days_before_checkin' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // Menyimpan form Pengaturan tanpa mengisi ulang kunci rahasia tidak boleh menghapus kredensial yang sudah ada.
        static::saving(function (TenantSetting $settings) {
            if ($settings->exists && $settings->isDirty('gateway_credentials') && blank($settings->gateway_credentials)) {
                $settings->gateway_credentials = $settings->getOriginal('gateway_credentials');
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
