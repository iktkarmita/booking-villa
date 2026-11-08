<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Support\CurrentTenant;
use App\Support\Payment\FakeGateway;
use App\Support\PaymentService;
use Illuminate\Console\Command;
use Illuminate\Http\Request;

/** Simulasi callback "sudah dibayar" dari gateway uji coba. Hanya untuk pengembangan. */
class FakePaymentPaid extends Command
{
    protected $signature = 'payment:fake-paid {code : Kode booking, contoh BV-ABCD1234}';

    protected $description = 'Simulasikan pembayaran QRIS lunas lewat gateway uji coba (bukan production)';

    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->error('Perintah ini tidak tersedia di production.');

            return self::FAILURE;
        }

        $booking = Booking::withoutTenancy()->where('code', strtoupper(trim($this->argument('code'))))->first();

        if (! $booking) {
            $this->error('Booking tidak ditemukan.');

            return self::FAILURE;
        }

        $payment = Payment::withoutTenancy()
            ->where('booking_id', $booking->getKey())
            ->where('status', 'pending')
            ->where('gateway', 'fake')
            ->latest('id')
            ->first();

        if (! $payment) {
            $this->error('Tidak ada tagihan QRIS (gateway uji coba) yang menunggu untuk booking ini.');

            return self::FAILURE;
        }

        $tenant = Tenant::query()->findOrFail($booking->tenant_id);
        $secret = (string) (TenantSetting::query()->where('tenant_id', $tenant->getKey())->first()?->gateway_credentials['secret'] ?? '');

        $body = json_encode(['ref' => $payment->gateway_ref, 'status' => 'paid', 'amount' => $payment->amount]);

        $request = Request::create('/', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_SIGNATURE' => FakeGateway::sign($body, $secret),
        ], $body);

        $outcome = app(CurrentTenant::class)->runAs($tenant, fn () => PaymentService::handleWebhook($tenant, 'fake', $request));

        $this->line("Hasil: {$outcome['result']} (HTTP {$outcome['status']})");

        return $outcome['status'] === 200 ? self::SUCCESS : self::FAILURE;
    }
}
