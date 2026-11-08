<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\BookingRuleViolated;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\PaymentWebhookLog;
use App\Models\Plan;
use App\Models\Room;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Support\Availability;
use App\Support\BookingService;
use App\Support\CurrentTenant;
use App\Support\Payment\FakeGateway;
use App\Support\PaymentService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentGatewayTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'rahasia-tenant';

    private Tenant $tenant;

    private Room $room;

    private string $in;

    private string $out;

    protected function setUp(): void
    {
        parent::setUp();

        config(['booking.base_domain' => 'localhost']);

        $this->tenant = Tenant::factory()->create(['slug' => 'bunga', 'subdomain' => 'bunga']);
        Subscription::factory()->create([
            'tenant_id' => $this->tenant->id,
            'plan_id' => Plan::factory()->create(['max_rooms' => 10])->id,
        ]);
        $this->room = Room::factory()->create(['tenant_id' => $this->tenant->id, 'base_price' => 500000, 'max_guests' => 4]);

        $this->in = CarbonImmutable::today()->addDays(30)->toDateString();
        $this->out = CarbonImmutable::today()->addDays(33)->toDateString();
    }

    private function useGateway(array $extra = []): void
    {
        $settings = $this->tenant->settings;
        $settings->update(array_merge(['gateway' => 'fake', 'gateway_credentials' => ['secret' => self::SECRET]], $extra));
    }

    private function book(string $email = 'tamu@example.com', ?string $in = null, ?string $out = null): Booking
    {
        return BookingService::create(
            $this->room->fresh(),
            $in ?? $this->in,
            $out ?? $this->out,
            2,
            ['name' => 'Budi', 'email' => $email, 'phone' => '081234567890'],
        );
    }

    private function pending(Booking $booking): Payment
    {
        return Payment::withoutTenancy()->where('booking_id', $booking->id)->where('status', 'pending')->latest('id')->firstOrFail();
    }

    /** Kirim callback seperti gateway: JSON + tanda tangan HMAC. */
    private function webhook(array $payload, ?string $secret = self::SECRET, string $gateway = 'fake', string $tenant = 'bunga')
    {
        $body = json_encode($payload);
        $server = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'];

        if ($secret !== null) {
            $server['HTTP_X_SIGNATURE'] = FakeGateway::sign($body, $secret);
        }

        return $this->call('POST', "/webhooks/payment/{$gateway}/{$tenant}", [], [], [], $server, $body);
    }

    public function test_tanpa_gateway_tagihan_tetap_transfer_manual(): void
    {
        $payment = $this->pending($this->book());

        $this->assertSame('manual', $payment->gateway);
        $this->assertSame('manual', $payment->method);
        $this->assertNull($payment->gateway_ref);
    }

    public function test_dengan_gateway_tagihan_menjadi_qris(): void
    {
        $this->useGateway();

        $payment = $this->pending($this->book());

        $this->assertSame('fake', $payment->gateway);
        $this->assertSame('qris', $payment->method);
        $this->assertStringStartsWith('FAKE-', $payment->gateway_ref);
        $this->assertNotEmpty($payment->qr_string);
        $this->assertSame(1500000, $payment->amount);
    }

    public function test_kredensial_gateway_tersimpan_terenkripsi_di_database(): void
    {
        $this->useGateway();

        $raw = \DB::table('tenant_settings')->where('tenant_id', $this->tenant->id)->value('gateway_credentials');

        $this->assertStringNotContainsString(self::SECRET, (string) $raw);
    }

    public function test_callback_sah_mengonfirmasi_booking(): void
    {
        $this->useGateway();
        $booking = $this->book();
        $payment = $this->pending($booking);

        $this->webhook(['ref' => $payment->gateway_ref, 'status' => 'paid', 'amount' => $payment->amount])
            ->assertOk()
            ->assertJson(['result' => 'processed']);

        $booking->refresh();
        $this->assertSame(BookingStatus::Confirmed, $booking->status);
        $this->assertSame(PaymentStatus::Paid, $booking->payment_status);
        $this->assertSame('paid', $payment->fresh()->status);

        $log = PaymentWebhookLog::query()->firstOrFail();
        $this->assertTrue($log->signature_valid);
        $this->assertSame('processed', $log->result);
        $this->assertSame($payment->id, $log->payment_id);
    }

    public function test_callback_ganda_tidak_menambah_pembayaran_dua_kali(): void
    {
        $this->useGateway();
        $booking = $this->book();
        $payment = $this->pending($booking);
        $payload = ['ref' => $payment->gateway_ref, 'status' => 'paid', 'amount' => $payment->amount];

        $this->webhook($payload)->assertOk();
        $this->webhook($payload)->assertOk()->assertJson(['result' => 'duplicate']);

        $this->assertSame(1500000, $booking->fresh()->paid_amount);
        $this->assertSame(2, PaymentWebhookLog::query()->count());
    }

    public function test_tanda_tangan_salah_ditolak_dan_dicatat(): void
    {
        $this->useGateway();
        $booking = $this->book();
        $payment = $this->pending($booking);
        $payload = ['ref' => $payment->gateway_ref, 'status' => 'paid', 'amount' => $payment->amount];

        $this->webhook($payload, 'kunci-salah')->assertStatus(401);
        $this->webhook($payload, null)->assertStatus(401);

        $this->assertSame(BookingStatus::PendingPayment, $booking->fresh()->status);
        $this->assertSame(2, PaymentWebhookLog::query()->where('result', 'invalid_signature')->where('signature_valid', false)->count());
    }

    public function test_nominal_tidak_cocok_ditolak(): void
    {
        $this->useGateway();
        $booking = $this->book();
        $payment = $this->pending($booking);

        $this->webhook(['ref' => $payment->gateway_ref, 'status' => 'paid', 'amount' => 1000])
            ->assertStatus(422)
            ->assertJson(['result' => 'amount_mismatch']);

        $this->assertSame(BookingStatus::PendingPayment, $booking->fresh()->status);
        $this->assertSame('pending', $payment->fresh()->status);
    }

    public function test_referensi_tidak_dikenal_dan_tenant_tidak_dikenal(): void
    {
        $this->useGateway();

        $this->webhook(['ref' => 'FAKE-TIDAKADA', 'status' => 'paid', 'amount' => 1])->assertNotFound();
        $this->webhook(['ref' => 'x', 'status' => 'paid', 'amount' => 1], self::SECRET, 'fake', 'tidak-ada')->assertNotFound();
    }

    public function test_callback_ke_gateway_yang_tidak_dipakai_tenant_ditolak(): void
    {
        // Tenant masih memakai transfer manual.
        $this->webhook(['ref' => 'x', 'status' => 'paid', 'amount' => 1])->assertNotFound();

        $this->assertSame('gateway_mismatch', PaymentWebhookLog::query()->firstOrFail()->result);
    }

    public function test_callback_tenant_lain_tidak_bisa_membayar_booking_tenant_ini(): void
    {
        $this->useGateway();
        $booking = $this->book();
        $payment = $this->pending($booking);

        $other = Tenant::factory()->create(['slug' => 'lain', 'subdomain' => 'lain']);
        $other->settings->update(['gateway' => 'fake', 'gateway_credentials' => ['secret' => self::SECRET]]);

        $this->webhook(['ref' => $payment->gateway_ref, 'status' => 'paid', 'amount' => $payment->amount], self::SECRET, 'fake', 'lain')
            ->assertNotFound();

        $this->assertSame(BookingStatus::PendingPayment, $booking->fresh()->status);
    }

    public function test_pembayaran_terlambat_dicatat_tanpa_menghidupkan_booking(): void
    {
        $this->useGateway();
        $booking = $this->book();
        $payment = $this->pending($booking);

        BookingService::expire($booking);

        $this->webhook(['ref' => $payment->gateway_ref, 'status' => 'paid', 'amount' => $payment->amount])
            ->assertOk()
            ->assertJson(['result' => 'late_payment']);

        $this->assertSame(BookingStatus::Expired, $booking->fresh()->status);
        $this->assertSame(0, $booking->fresh()->paid_amount);
        $this->assertNotNull($payment->fresh()->note);
    }

    public function test_callback_gagal_menutup_tagihan_tanpa_mengonfirmasi(): void
    {
        $this->useGateway();
        $booking = $this->book();
        $payment = $this->pending($booking);

        $this->webhook(['ref' => $payment->gateway_ref, 'status' => 'failed', 'amount' => $payment->amount])->assertOk();

        $this->assertSame('failed', $payment->fresh()->status);
        $this->assertSame(BookingStatus::PendingPayment, $booking->fresh()->status);
    }

    public function test_dp_lalu_pelunasan_lewat_qris(): void
    {
        $this->useGateway(['payment_mode' => 'dp', 'dp_percent' => 30]);
        $booking = $this->book();
        $dp = $this->pending($booking);

        $this->webhook(['ref' => $dp->gateway_ref, 'status' => 'paid', 'amount' => $dp->amount])->assertOk();

        $booking->refresh();
        $this->assertSame(PaymentStatus::Partial, $booking->payment_status);

        $balance = PaymentService::requestBalance($booking);

        $this->assertSame('balance', $balance->type);
        $this->assertSame('qris', $balance->method);
        $this->assertSame(1050000, $balance->amount);

        // Meminta lagi memakai tagihan yang sama.
        $this->assertSame($balance->id, PaymentService::requestBalance($booking->fresh())->id);

        $this->webhook(['ref' => $balance->gateway_ref, 'status' => 'paid', 'amount' => $balance->amount])->assertOk();

        $booking->refresh();
        $this->assertSame(PaymentStatus::Paid, $booking->payment_status);
        $this->assertSame(0, $booking->remaining());
    }

    public function test_pelunasan_ditolak_bila_sudah_lunas_atau_belum_terkonfirmasi(): void
    {
        $this->useGateway();
        $booking = $this->book();

        try {
            PaymentService::requestBalance($booking);
            $this->fail('belum terkonfirmasi');
        } catch (BookingRuleViolated $e) {
            $this->assertSame('not_payable', $e->reason);
        }

        BookingService::confirmPayment($this->pending($booking));

        $this->expectException(BookingRuleViolated::class);

        PaymentService::requestBalance($booking->fresh());
    }

    public function test_tagihan_pelunasan_kedaluwarsa_ditutup_oleh_perintah_terjadwal(): void
    {
        $this->useGateway(['payment_mode' => 'dp', 'dp_percent' => 30]);
        $booking = $this->book();
        BookingService::confirmPayment($this->pending($booking));

        $balance = PaymentService::requestBalance($booking->fresh());
        $balance->update(['expires_at' => now()->subMinute()]);

        $this->artisan('bookings:expire')->assertSuccessful();

        $this->assertSame('expired', $balance->fresh()->status);
        $this->assertSame(BookingStatus::Confirmed, $booking->fresh()->status);
        $this->assertFalse(Availability::isAvailable($this->room, $this->in, $this->out));
    }

    public function test_halaman_status_menampilkan_qris_dan_tombol_pelunasan(): void
    {
        $this->useGateway(['payment_mode' => 'dp', 'dp_percent' => 30]);
        $booking = $this->book();

        $this->get('http://bunga.localhost/booking/'.$booking->code)
            ->assertOk()
            ->assertSee('QRIS')
            ->assertDontSee('Unggah bukti transfer');

        BookingService::confirmPayment($this->pending($booking));

        $this->get('http://bunga.localhost/booking/'.$booking->code)
            ->assertOk()
            ->assertSee('Bayar pelunasan');
    }

    public function test_tamu_bisa_meminta_pelunasan_dari_halaman_status(): void
    {
        $this->useGateway(['payment_mode' => 'dp', 'dp_percent' => 30]);
        $booking = $this->book();
        BookingService::confirmPayment($this->pending($booking));

        app(CurrentTenant::class)->set($this->tenant);

        \Livewire\Livewire::test(\App\Livewire\Site\BookingStatus::class, ['code' => $booking->code])
            ->call('payBalance')
            ->assertHasNoErrors()
            ->assertSee('QRIS');

        $this->assertSame(1, Payment::withoutTenancy()->where('booking_id', $booking->id)->where('type', 'balance')->count());
    }

    public function test_perintah_fake_paid_mensimulasikan_pembayaran(): void
    {
        $this->useGateway();
        $booking = $this->book();

        $this->artisan('payment:fake-paid', ['code' => strtolower($booking->code)])
            ->expectsOutputToContain('processed')
            ->assertSuccessful();

        $this->assertSame(BookingStatus::Confirmed, $booking->fresh()->status);

        $this->artisan('payment:fake-paid', ['code' => $booking->code])->assertFailed();
        $this->artisan('payment:fake-paid', ['code' => 'BV-TIDAKADA'])->assertFailed();
    }
}
