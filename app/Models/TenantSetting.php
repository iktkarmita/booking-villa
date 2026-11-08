<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantSetting extends Model
{
    protected $guarded = [];

    protected $hidden = ['gateway_credentials', 'wa_credentials'];

    protected function casts(): array
    {
        return [
            // Kredensial disimpan terenkripsi (butuh APP_KEY).
            'gateway_credentials' => 'encrypted:array',
            'wa_credentials' => 'encrypted:array',
            'cancellation_policy' => 'array',
            'dp_percent' => 'integer',
            'balance_due_days_before_checkin' => 'integer',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
