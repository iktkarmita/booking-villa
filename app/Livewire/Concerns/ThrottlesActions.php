<?php

namespace App\Livewire\Concerns;

use Illuminate\Support\Facades\RateLimiter;

/** Pembatas laju per IP untuk aksi Livewire publik (booking, refund, unggah bukti). */
trait ThrottlesActions
{
    /** Mengembalikan true (dan mengisi error) bila batas terlampaui; bila belum, hitungan bertambah. */
    protected function throttled(string $action, int $maxAttempts, string $errorKey, int $decaySeconds = 60): bool
    {
        $key = 'site:'.$action.':'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $this->addError($errorKey, __('site.errors.throttled', ['seconds' => RateLimiter::availableIn($key)]));

            return true;
        }

        RateLimiter::hit($key, $decaySeconds);

        return false;
    }
}
