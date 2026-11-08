<?php

namespace App\Http\Middleware;

use App\Support\CurrentTenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Urutan bahasa: pilihan di sesi -> bahasa default tenant -> bahasa default aplikasi. */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = app(CurrentTenant::class)->get();

        $candidates = [
            $request->hasSession() ? $request->session()->get('locale') : null,
            $tenant?->default_locale,
            config('booking.default_locale'),
        ];

        foreach ($candidates as $locale) {
            if (is_string($locale) && in_array($locale, config('booking.locales', []), true)) {
                app()->setLocale($locale);
                break;
            }
        }

        return $next($request);
    }
}
