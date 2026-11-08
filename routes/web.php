<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\LocaleController;
use Illuminate\Support\Facades\Route;

/*
| Rute publik. Panel Filament (/platform dan /admin) didaftarkan oleh panel provider.
| Tenant dikenali dari host oleh middleware tenant.resolve.
*/
Route::middleware(['tenant.resolve', 'tenant.active', 'locale'])->group(function () {
    Route::get('/', HomeController::class)->name('home');
    Route::get('/lang/{locale}', LocaleController::class)->name('locale');
});
