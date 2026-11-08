<?php

use App\Http\Controllers\Webhook\PaymentWebhookController;
use Illuminate\Support\Facades\Route;

/*
| Callback gateway: tanpa grup middleware "web" (tanpa sesi dan CSRF), keamanan lewat
| verifikasi tanda tangan per tenant. Dibatasi lajunya per IP.
*/
Route::middleware('throttle:120,1')
    ->post('/webhooks/payment/{gateway}/{tenant}', PaymentWebhookController::class)
    ->name('webhooks.payment');
