<?php

namespace App\Http\Controllers;

use App\Support\CurrentTenant;
use Illuminate\Http\Response;

/** Placeholder fase 0. Diganti komponen Livewire daftar villa di fase 3. */
class HomeController extends Controller
{
    public function __invoke(CurrentTenant $tenancy): Response
    {
        $tenant = $tenancy->get();

        if ($tenant === null) {
            return response('<h1>Booking Villa</h1>');
        }

        return response(
            '<h1>'.e($tenant->name).'</h1><p>'.e(__('booking.welcome')).'</p>'
        );
    }
}
