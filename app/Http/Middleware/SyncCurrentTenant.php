<?php

namespace App\Http\Middleware;

use App\Support\CurrentTenant;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Menyalin tenant aktif Filament (panel owner) ke CurrentTenant agar global scope ikut membatasi data. */
class SyncCurrentTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = Filament::getTenant();

        if ($tenant !== null) {
            app(CurrentTenant::class)->set($tenant);

            // Bahasa panel mengikuti default tenant kecuali pemilik sudah memilih bahasa di sesi.
            $chosen = $request->hasSession() ? $request->session()->get('locale') : null;
            if (! in_array($chosen, config('booking.locales', []), true) && in_array($tenant->default_locale, config('booking.locales', []), true)) {
                app()->setLocale($tenant->default_locale);
            }

            // Tanggal/jam di panel tampil dalam zona waktu tenant (mis. WITA).
            if (filled($tenant->timezone) && class_exists(\Filament\Support\Facades\FilamentTimezone::class)) {
                \Filament\Support\Facades\FilamentTimezone::set($tenant->timezone);
            }
        }

        return $next($request);
    }
}
