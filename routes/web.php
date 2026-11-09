<?php

use App\Http\Controllers\LocaleController;
use App\Http\Controllers\SiteController;
use Illuminate\Support\Facades\Route;

/*
| Rute publik. Panel Filament (/platform dan /admin) didaftarkan oleh panel provider.
| Tenant dikenali dari host oleh middleware tenant.resolve.
*/
Route::middleware(['tenant.resolve', 'tenant.active', 'locale'])->group(function () {
    Route::get('/', [SiteController::class, 'home'])->name('home');
    Route::get('/lang/{locale}', LocaleController::class)->name('locale');

    Route::middleware('tenant.required')->group(function () {
        Route::get('/villas/{slug}', [SiteController::class, 'villa'])->name('site.villa');
        Route::get('/book/{room}', [SiteController::class, 'checkout'])->whereNumber('room')->name('site.checkout');
        Route::get('/booking/{code}', [SiteController::class, 'booking'])->middleware('throttle:60,1')->name('site.booking');
    });
});
