<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Catatan semua callback gateway (tanpa scope tenant: tetap tersimpan walau tenant/pembayaran tidak dikenal). */
class PaymentWebhookLog extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'signature_valid' => 'boolean',
            'processed_at' => 'datetime',
        ];
    }
}
