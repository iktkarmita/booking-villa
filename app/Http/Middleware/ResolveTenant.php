<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Support\CurrentTenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menentukan tenant dari host:
 *  - host induk / host pusat / subdomain reserved  -> konteks pusat (tanpa tenant)
 *  - {subdomain}.{base_domain}                      -> cari berdasarkan tenants.subdomain
 *  - host lain                                      -> cari berdasarkan tenants.custom_domain
 * Tenant tidak ditemukan -> 404.
 */
class ResolveTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenancy = app(CurrentTenant::class);
        $tenancy->forget();

        $host = strtolower($request->getHost());
        $base = strtolower((string) config('booking.base_domain'));

        if ($host === $base || in_array($host, config('booking.central_hosts', []), true)) {
            return $next($request);
        }

        $suffix = '.'.$base;

        if (str_ends_with($host, $suffix)) {
            $subdomain = substr($host, 0, -strlen($suffix));

            if (in_array($subdomain, config('booking.reserved_subdomains', []), true)) {
                return $next($request);
            }

            $tenant = Tenant::query()->where('subdomain', $subdomain)->first();
        } else {
            $tenant = Tenant::query()->where('custom_domain', $host)->first();
        }

        abort_if($tenant === null, 404);

        $tenancy->set($tenant);

        return $next($request);
    }
}
