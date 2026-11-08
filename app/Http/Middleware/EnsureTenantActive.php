<?php

namespace App\Http\Middleware;

use App\Support\CurrentTenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Situs publik tenant yang di-suspend (mis. menunggak langganan) tidak melayani pengunjung. */
class EnsureTenantActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = app(CurrentTenant::class)->get();

        abort_if($tenant !== null && $tenant->isSuspended(), 503, __('booking.tenant_suspended'));

        return $next($request);
    }
}
