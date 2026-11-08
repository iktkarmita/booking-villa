<?php

namespace App\Http\Middleware;

use App\Support\CurrentTenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Bahasa panel: pilihan di sesi, lalu default tenant (panel owner), lalu BOOKING_PANEL_LOCALE (default Indonesia). */
class SetPanelLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->hasSession() ? $request->session()->get('locale') : null;

        if (! in_array($locale, config('booking.locales', []), true)) {
            $locale = app(CurrentTenant::class)->get()?->default_locale ?: config('booking.panel_locale');
        }

        if (is_string($locale) && in_array($locale, config('booking.locales', []), true)) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
