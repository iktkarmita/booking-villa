<?php

namespace App\Support;

use App\Models\Tenant;
use Closure;

/**
 * Menyimpan tenant yang sedang aktif pada request/proses ini.
 * Didaftarkan sebagai singleton di BookingServiceProvider.
 */
final class CurrentTenant
{
    private ?Tenant $tenant = null;

    public function set(?Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function get(): ?Tenant
    {
        return $this->tenant;
    }

    public function has(): bool
    {
        return $this->tenant !== null;
    }

    public function id(): ?int
    {
        return $this->tenant?->getKey();
    }

    public function forget(): void
    {
        $this->tenant = null;
    }

    /** Jalankan callback dalam konteks tenant tertentu (mis. di job/command), lalu kembalikan konteks lama. */
    public function runAs(Tenant $tenant, Closure $callback): mixed
    {
        $previous = $this->tenant;
        $this->tenant = $tenant;

        try {
            return $callback();
        } finally {
            $this->tenant = $previous;
        }
    }
}
