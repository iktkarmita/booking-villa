<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\BookingRuleViolated;
use App\Livewire\Site\BookingStatus as BookingStatusComponent;
use App\Mail\SimpleMail;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Refund;
use App\Models\Room;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Models\User;
use App\Support\Availability;
use App\Support\BookingService;
use App\Support\CurrentTenant;
use App\Support\RefundPolicy;
use App\Support\RefundService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class RefundNotificationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Room $room;

    private string $in;

    private string $out;

    protected function setUp(): void
    {
        parent::setUp();

        config(['booking.base_domain' => 'localhost', 'app.url' => 'http://localhost:8000']);
        app()->setLocale('id');
        Mail::fake();

        $this->tenant = Tenant::factory()->create(['slug' => 'bunga', 'subdomain' => 'bunga', 'name' => 'Villa Bunga']);
        Subscription::factory()->create([
            'tenant_id' => $this->tenant->id,
            'plan_id' => Plan::factory()->create(['max_rooms' => 10])->id,
        ]);
        $this->room = Room::factory()->create(['tenant_id' => $this->tenant->id, 'base_price' => 500000, 'max_guests' => 4]);

        $this->in = CarbonImmutable::today()->addDays(30)->toDateString();
        $this->out = CarbonImmutable::today()->addDays(33)->toDateString();
    }

    private function settings(array $values): void
    {
        $this->tenant->settings->update($values);
    }

    /** @param int $offsetDays geser tanggal supaya beberapa booking bisa hidup berdampingan */
    private function book(string $locale = 'id', string $email = 'tamu@example.com', int $offsetDays = 0): Booking
    {
        return BookingService::create(
            $this->room->fresh(),
            CarbonImmutable::parse($this->in)->addDays($offsetDays)->toDateString(),
            CarbonImmutable::parse($this->out)->addDays($offsetDays)->toDateString(),
            2,
            ['name' => 'Budi', 'email' => $email, 'phone' => '081234567890'],
            null,
            $locale,
        );
    }

    /** Booking terkonfirmasi, dibayar penuh (Rp 1.500.000). */
    private function paidBooking(int $offsetDays = 0): Booking
    {
        $booking = $this->book('id', 'tamu@example.com', $offsetDays);

        return BookingService::confirmPayment(Payment::withoutTenancy()->where('booking_id', $booking->id)->firstOrFail());
    }

    private function dest(): array
    {
        return ['bank' => 'BCA', 'name' => 'Budi', 'number' => '5551234567'];
    }

    // ---------- Kebijakan refund ----------

    public function test_tingkat_refund_bawaan_dan_kustom(): void
    {
        $settings = $this->tenant->settings;
        $today = CarbonImmutable::parse('2026-12-01');

        $this->assertSame(100, RefundPolicy::percent($settings, CarbonImmutable::parse('2026-12-11'), $today)); // H-10
        $this->assertSame(100, RefundPolicy::percent($settings, CarbonImmutable::parse('2026-12-08'), $today)); // H-7
        $this->assertSame(50, RefundPolicy::percent($settings, CarbonImmutable::parse('2026-12-06'), $today)); // H-5
        $this->assertSame(50, RefundPolicy::percent($settings, CarbonImmutable::parse('2026-12-04'), $today)); // H-3
        $this->assertSame(0, RefundPolicy::percent($settings, CarbonImmutable::parse('2026-12-03'), $today)); // H-2
        $this->assertSame(0, RefundPolicy::percent($settings, CarbonImmutable::parse('2026-11-30'), $today)); // lewat

        $settings->update(['refund_tiers' => [['days' => 14, 'percent' => 80], ['days' => 1, 'percent' => 20]]]);

        $this->assertSame(80, RefundPolicy::percent($settings->fresh(), CarbonImmutable::parse('2026-12-20'), $today));
        $this->assertSame(20, RefundPolicy::percent($settings->fresh(), CarbonImmutable::parse('2026-12-05'), $today));

        $settings->update(['refund_tiers' => []]);

        $this->assertSame(0, RefundPolicy::percent($settings->fresh(), CarbonImmutable::parse('2026-12-30'), $today));
    }

    public function test_tenant_baru_punya_tingkat_refund_bawaan(): void
    {
        $this->assertSame(RefundPolicy::DEFAULT_TIERS, $this->tenant->settings->fresh()->refund_tiers);
    }

    // ---------- Pengajuan dan keputusan ----------

    public function test_tamu_mengajukan_pembatalan_dengan_saran_nominal_dari_kebijakan(): void
    {
        $this->settings(['notify_email' => 'owner@example.com']);
        $booking = $this->paidBooking();

        $refund = RefundService::request($booking, 'Berhalangan hadir', $this->dest());

        $this->assertSame('requested', $refund->status);
        $this->assertSame('guest', $refund->requested_by);
        $this->assertSame(1500000, $refund->suggested_amount); // H-30 -> 100%
        $this->assertSame(BookingStatus::Confirmed, $booking->fresh()->status); // belum dibatalkan

        Mail::assertSent(SimpleMail::class, fn (SimpleMail $m) => $m->hasTo('owner@example.com') && str_contains($m->mailSubject, $booking->code));
    }

    public function test_nomor_rekening_refund_tersimpan_terenkripsi(): void
    {
        $refund = RefundService::request($this->paidBooking(), 'x', $this->dest());

        $raw = DB::table('refunds')->where('id', $refund->id)->value('dest_account_number');

        $this->assertStringNotContainsString('5551234567', (string) $raw);
        $this->assertSame('5551234567', $refund->fresh()->dest_account_number);
    }

    public function test_pengajuan_ditolak_untuk_booking_belum_dibayar_dan_pengajuan_ganda(): void
    {
        try {
            RefundService::request($this->book(), 'x', $this->dest());
            $this->fail('belum dibayar');
        } catch (BookingRuleViolated $e) {
            $this->assertSame('refund_not_allowed', $e->reason);
        }

        $booking = $this->paidBooking(10);
        RefundService::request($booking, 'x', $this->dest());

        $this->expectException(BookingRuleViolated::class);

        RefundService::request($booking, 'lagi', $this->dest());
    }

    public function test_owner_menyetujui_dengan_nominal_sendiri_booking_dibatalkan_dan_tanggal_bebas(): void
    {
        $booking = $this->paidBooking();
        $refund = RefundService::request($booking, 'x', $this->dest());

        $refund = RefundService::approve($refund, 1000000, 'Dipotong biaya admin');

        $this->assertSame('approved', $refund->status);
        $this->assertSame(1000000, $refund->amount_approved);
        $this->assertSame(BookingStatus::Cancelled, $booking->fresh()->status);
        $this->assertTrue(Availability::isAvailable($this->room, $this->in, $this->out));
    }

    public function test_nominal_melebihi_yang_dibayar_ditolak_tanpa_mengubah_apa_pun(): void
    {
        $booking = $this->paidBooking();
        $refund = RefundService::request($booking, 'x', $this->dest());

        try {
            RefundService::approve($refund, 9999999);
            $this->fail('refund_amount');
        } catch (BookingRuleViolated $e) {
            $this->assertSame('refund_amount', $e->reason);
        }

        $this->assertSame('requested', $refund->fresh()->status);
        $this->assertSame(BookingStatus::Confirmed, $booking->fresh()->status);
    }

    public function test_setujui_nominal_nol_langsung_selesai(): void
    {
        $booking = $this->paidBooking();
        $refund = RefundService::approve(RefundService::request($booking, 'x', $this->dest()), 0);

        $this->assertSame('refunded', $refund->status);
        $this->assertSame(BookingStatus::Cancelled, $booking->fresh()->status);
        $this->assertSame(PaymentStatus::Paid, $booking->fresh()->payment_status);
    }

    public function test_refund_sebagian_dan_penuh_menandai_status_pembayaran(): void
    {
        $partial = RefundService::approve(RefundService::request($this->paidBooking(), 'x', $this->dest()), 1000000);
        $partial = RefundService::markRefunded($partial, 'refund-proofs/a.jpg', 'Sudah transfer');

        $this->assertSame('refunded', $partial->status);
        $this->assertSame('refund-proofs/a.jpg', $partial->proof_path);
        $this->assertNotNull($partial->refunded_at);
        $this->assertSame(PaymentStatus::Paid, $partial->booking->fresh()->payment_status);

        // Booking kedua di tanggal yang sudah bebas: refund penuh.
        $full = RefundService::approve(RefundService::request($this->paidBooking(), 'x', $this->dest()), 1500000);
        RefundService::markRefunded($full);

        $this->assertSame(PaymentStatus::Refunded, $full->booking->fresh()->payment_status);
        $this->assertSame(1, Payment::withoutTenancy()->where('booking_id', $full->booking_id)->where('status', 'refunded')->count());
    }

    public function test_hanya_refund_disetujui_yang_bisa_ditandai_selesai_dan_keputusan_tidak_bisa_diulang(): void
    {
        $refund = RefundService::request($this->paidBooking(), 'x', $this->dest());

        try {
            RefundService::markRefunded($refund);
            $this->fail('belum disetujui');
        } catch (BookingRuleViolated $e) {
            $this->assertSame('refund_not_pending', $e->reason);
        }

        RefundService::reject($refund, 'Sudah lewat batas');

        $this->expectException(BookingRuleViolated::class);

        RefundService::approve($refund->fresh(), 100);
    }

    public function test_pengajuan_ditolak_booking_tetap_berjalan_dan_tamu_bisa_mengajukan_lagi(): void
    {
        $booking = $this->paidBooking();
        $refund = RefundService::reject(RefundService::request($booking, 'x', $this->dest()), 'Tidak sesuai kebijakan');

        $this->assertSame('rejected', $refund->status);
        $this->assertSame(BookingStatus::Confirmed, $booking->fresh()->status);

        $this->assertSame('requested', RefundService::request($booking, 'coba lagi', $this->dest())->status);
    }

    public function test_owner_membatalkan_booking_dengan_atau_tanpa_refund(): void
    {
        $withRefund = RefundService::ownerCancel($this->paidBooking(), 'Villa renovasi', 1500000);

        $this->assertSame(BookingStatus::Cancelled, $withRefund->status);
        $refund = Refund::withoutTenancy()->where('booking_id', $withRefund->id)->firstOrFail();
        $this->assertSame('owner', $refund->requested_by);
        $this->assertSame('approved', $refund->status);
        $this->assertSame(1500000, $refund->amount_approved);

        $noRefund = RefundService::ownerCancel($this->paidBooking(), 'Tamu tidak hadir', 0);

        $this->assertSame(BookingStatus::Cancelled, $noRefund->status);
        $this->assertSame(0, Refund::withoutTenancy()->where('booking_id', $noRefund->id)->count());
    }

    public function test_owner_cancel_menolak_nominal_berlebih(): void
    {
        $booking = $this->paidBooking();

        try {
            RefundService::ownerCancel($booking, 'x', 99999999);
            $this->fail('refund_amount');
        } catch (BookingRuleViolated $e) {
            $this->assertSame('refund_amount', $e->reason);
        }

        $this->assertSame(BookingStatus::Confirmed, $booking->fresh()->status);
    }

    // ---------- Email ----------

    public function test_booking_baru_mengirim_email_ke_tamu_dengan_tautan_booking(): void
    {
        $booking = $this->book();

        Mail::assertSent(SimpleMail::class, fn (SimpleMail $m) => $m->hasTo('tamu@example.com')
            && str_contains($m->mailSubject, $booking->code)
            && str_contains($m->body, 'Halo Budi')
            && $m->url === 'http://bunga.localhost:8000/booking/'.$booking->code);
    }

    public function test_booking_baru_mengirim_email_ke_owner_bila_email_notifikasi_diisi(): void
    {
        // Bahasa platform 'en' tidak boleh memengaruhi email owner: yang dipakai bahasa default tenant.
        config(['booking.default_locale' => 'en']);
        $this->settings(['notify_email' => 'owner@example.com']);

        $booking = $this->book();

        Mail::assertSent(SimpleMail::class, fn (SimpleMail $m) => $m->hasTo('owner@example.com')
            && str_contains($m->mailSubject, 'Booking baru')
            && str_contains($m->mailSubject, $booking->code)
            && $m->url === 'http://bunga.localhost:8000/admin/bunga/bookings');
        Mail::assertSent(SimpleMail::class, 2);
    }

    public function test_nama_pengirim_adalah_nama_villa_dan_balasan_ke_owner(): void
    {
        config(['mail.from.address' => 'noreply@example.com']);
        $this->settings(['notify_email' => 'owner@example.com']);

        $this->book();

        Mail::assertSent(SimpleMail::class, function (SimpleMail $m) {
            $envelope = $m->envelope();

            return $m->hasTo('tamu@example.com')
                && $envelope->from?->address === 'noreply@example.com'
                && $envelope->from?->name === 'Villa Bunga'
                && ($envelope->replyTo[0]->address ?? null) === 'owner@example.com';
        });
    }

    public function test_tanpa_email_owner_hanya_tamu_yang_dikirimi(): void
    {
        $this->book();

        Mail::assertSent(SimpleMail::class, 1);
    }

    public function test_email_mengikuti_bahasa_booking(): void
    {
        $booking = $this->book('en');

        Mail::assertSent(SimpleMail::class, fn (SimpleMail $m) => $m->hasTo('tamu@example.com') && str_contains($m->body, 'Hello Budi') && str_contains($m->mailSubject, 'received'));

        BookingService::confirmPayment(Payment::withoutTenancy()->where('booking_id', $booking->id)->firstOrFail());

        Mail::assertSent(SimpleMail::class, fn (SimpleMail $m) => str_contains($m->mailSubject, 'confirmed'));
    }

    public function test_pembayaran_pembatalan_dan_refund_memicu_email(): void
    {
        $booking = $this->paidBooking();

        Mail::assertSent(SimpleMail::class, fn (SimpleMail $m) => $m->hasTo('tamu@example.com') && str_contains($m->mailSubject, 'terkonfirmasi'));

        $refund = RefundService::approve(RefundService::request($booking, 'x', $this->dest()), 750000);

        Mail::assertSent(SimpleMail::class, fn (SimpleMail $m) => str_contains($m->mailSubject, 'dibatalkan'));
        Mail::assertSent(SimpleMail::class, fn (SimpleMail $m) => str_contains($m->mailSubject, 'disetujui') && str_contains($m->body, '750.000'));

        RefundService::markRefunded($refund);

        Mail::assertSent(SimpleMail::class, fn (SimpleMail $m) => str_contains($m->mailSubject, 'sudah ditransfer'));
    }

    public function test_gagal_kirim_email_tidak_menggagalkan_booking(): void
    {
        Mail::swap(app('mail.manager'));
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp' => ['transport' => 'smtp', 'host' => '127.0.0.1', 'port' => 1, 'timeout' => 1],
        ]);

        $booking = $this->book();

        $this->assertNotNull($booking->id);
        $this->assertSame(BookingStatus::PendingPayment, $booking->fresh()->status);
    }

    public function test_pengingat_pelunasan_dikirim_sekali(): void
    {
        $this->settings(['payment_mode' => 'dp', 'dp_percent' => 30]);
        $booking = $this->book();
        $booking = BookingService::confirmPayment(Payment::withoutTenancy()->where('booking_id', $booking->id)->firstOrFail());
        $booking->update(['balance_due_date' => CarbonImmutable::today()->addDay()->toDateString()]);

        $this->artisan('bookings:remind-balance')->assertSuccessful();

        Mail::assertSent(SimpleMail::class, fn (SimpleMail $m) => str_contains($m->mailSubject, 'Pengingat pelunasan') && str_contains($m->body, '1.050.000'));
        $this->assertNotNull($booking->fresh()->balance_reminded_at);

        $this->artisan('bookings:remind-balance')->assertSuccessful();

        Mail::assertSent(SimpleMail::class, fn (SimpleMail $m) => str_contains($m->mailSubject, 'Pengingat pelunasan'), 1);
    }

    public function test_pengingat_tidak_dikirim_untuk_pelunasan_yang_masih_jauh(): void
    {
        $this->settings(['payment_mode' => 'dp', 'dp_percent' => 30]);
        $booking = $this->book();
        BookingService::confirmPayment(Payment::withoutTenancy()->where('booking_id', $booking->id)->firstOrFail());
        // balance_due_date bawaan: H-7 dari check-in (+30 hari) -> masih jauh

        $this->artisan('bookings:remind-balance')->assertSuccessful();

        Mail::assertNotSent(SimpleMail::class, fn (SimpleMail $m) => str_contains($m->mailSubject, 'Pengingat'));
    }

    // ---------- Halaman tamu dan panel owner ----------

    public function test_tamu_mengajukan_pembatalan_dari_halaman_status(): void
    {
        $booking = $this->paidBooking();
        app(CurrentTenant::class)->set($this->tenant);

        Livewire::test(BookingStatusComponent::class, ['code' => $booking->code])
            ->call('requestCancel')
            ->assertHasErrors(['cancelReason', 'destBank', 'destName', 'destNumber'])
            ->set('cancelReason', 'Sakit')
            ->set('destBank', 'BCA')
            ->set('destName', 'Budi')
            ->set('destNumber', '5551234567')
            ->call('requestCancel')
            ->assertHasNoErrors()
            ->assertSee('sedang diperiksa owner');

        $this->assertSame(1, Refund::withoutTenancy()->where('booking_id', $booking->id)->where('status', 'requested')->count());
    }

    public function test_halaman_status_menampilkan_tombol_batal_hanya_untuk_booking_terkonfirmasi(): void
    {
        $pending = $this->book();

        $this->get('http://bunga.localhost/booking/'.$pending->code)->assertOk()->assertDontSee('Batalkan booking ini');

        $paid = $this->paidBooking(10);

        $this->get('http://bunga.localhost/booking/'.$paid->code)->assertOk()->assertSee('Batalkan booking ini');
    }

    public function test_owner_membuka_halaman_refund_dan_pengaturan(): void
    {
        $refund = RefundService::request($this->paidBooking(), 'Sakit', $this->dest());
        $owner = User::factory()->create();
        $this->tenant->users()->attach($owner, ['role' => 'owner']);

        $this->actingAs($owner);

        $this->get('/admin/bunga/refunds')->assertOk()->assertSee($refund->booking->code);
        $this->get('/admin/bunga/tenant-settings')->assertOk();
        $this->get('/admin/bunga/bookings')->assertOk();
    }

    public function test_pengaturan_tingkat_refund_milik_tenant_lain_tidak_bocor(): void
    {
        $other = Tenant::factory()->create(['slug' => 'lain', 'subdomain' => 'lain']);
        $other->settings->update(['refund_tiers' => []]);

        $this->assertSame(RefundPolicy::DEFAULT_TIERS, RefundPolicy::tiers(TenantSetting::query()->where('tenant_id', $this->tenant->id)->first()));
        $this->assertSame([], RefundPolicy::tiers(TenantSetting::query()->where('tenant_id', $other->id)->first()));
    }
}
