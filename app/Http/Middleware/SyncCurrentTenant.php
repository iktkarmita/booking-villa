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
        }

        return $next($request);
    }
}
