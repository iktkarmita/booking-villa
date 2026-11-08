<?php

namespace App\Http\Middleware;

use App\Support\CurrentTenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Halaman khusus tenant (villa, checkout, booking) tidak tersedia di host pusat. */
class RequireTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(app(CurrentTenant::class)->has(), 404);

        return $next($request);
    }
}
