<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('bookings:expire')->everyMinute()->withoutOverlapping();
Schedule::command('bookings:remind-balance')->dailyAt('09:00')->withoutOverlapping();
Schedule::command('subscriptions:process')->dailyAt('01:00')->withoutOverlapping();
