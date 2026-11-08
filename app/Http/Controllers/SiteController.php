<?php

namespace App\Http\Controllers;

use App\Support\CurrentTenant;
use Illuminate\Http\Request;
use Illuminate\Contracts\View\View;

/** Halaman publik tamu. Isi interaktif ada di komponen Livewire (App\Livewire\Site). */
class SiteController extends Controller
{
    public function home(CurrentTenant $tenancy): View
    {
        return $tenancy->has() ? view('site.home') : view('site.central');
    }

    public function villa(string $slug): View
    {
        return view('site.villa', ['slug' => $slug]);
    }

    public function checkout(Request $request, int $room): View
    {
        return view('site.checkout', [
            'roomId' => $room,
            'checkIn' => (string) $request->query('check_in', ''),
            'checkOut' => (string) $request->query('check_out', ''),
            'guests' => max(1, (int) $request->query('guests', 2)),
        ]);
    }

    public function booking(string $code): View
    {
        return view('site.booking', ['code' => $code]);
    }
}
