<?php

namespace Tests\Feature;

use App\Enums\SubscriptionStatus;
use App\Enums\TenantStatus;
use App\Mail\SimpleMail;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use App\Models\Tenant;
use App\Models\User;
use App\Support\SubscriptionService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SubscriptionBillingTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Subscription $subscription;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->travelTo('2026-11-01 10:00:00');

        $this->tenant = Tenant::factory()->create(['default_locale' => 'id']);
        $owner = User::factory()->create(['email' => 'owner@villa.test']);
        $this->tenant->users()->attach($owner->id, ['role' => 'owner']);

        $plan = Plan::factory()->create(['price' => 250000, 'max_rooms' => 20, 'interval' => 'month']);
        $this->subscription = Subscription::factory()->create([
            'tenant_id' => $this->tenant->id,
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::Active,
            'starts_at' => '2026-10-01',
            'ends_at' => '2026-11-30',
        ]);
    }

    public function test_tagihan_dibuat_menjelang_berakhir_sekali_saja(): void
    {
        $this->assertSame(0, SubscriptionService::generateInvoices()); // masih 29 hari lagi

        $this->travelTo('2026-11-24 10:00:00');
        $this->assertSame(1, SubscriptionService::generateInvoices());
        $this->assertSame(0, SubscriptionService::generateInvoices()); // idempoten

        $invoice = SubscriptionInvoice::withoutTenancy()->first();
        $this->assertSame(250000, $invoice->amount);
        $this->assertSame('2026-12-01', $invoice->period_start->toDateString());
        $this->assertSame('2026-12-31', $invoice->period_end->toDateString());
        $this->assertSame('2026-11-30', $invoice->due_date->toDateString());
        $this->assertSame('unpaid', $invoice->status);
        Mail::assertSent(SimpleMail::class, fn ($m) => $m->hasTo('owner@villa.test'));
    }

    public function test_bayar_memperpanjang_dan_idempoten(): void
    {
        $this->travelTo('2026-11-25 10:00:00');
        SubscriptionService::generateInvoices();
        $invoice = SubscriptionInvoice::withoutTenancy()->first();

        SubscriptionService::markPaid($invoice);
        SubscriptionService::markPaid($invoice);

        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertSame('2026-12-31', $this->subscription->fresh()->ends_at->toDateString());
        $this->assertSame(SubscriptionStatus::Active, $this->subscription->fresh()->status);
    }

    public function test_tagihan_batal_tidak_bisa_dibayar(): void
    {
        $this->travelTo('2026-11-25 10:00:00');
        SubscriptionService::generateInvoices();
        $invoice = SubscriptionInvoice::withoutTenancy()->first();
        SubscriptionService::void($invoice);

        $this->expectException(DomainException::class);
        SubscriptionService::markPaid($invoice->fresh());
    }

    public function test_telat_menunggak_lalu_ditangguhkan_lalu_aktif_setelah_bayar(): void
    {
        $this->travelTo('2026-11-25 10:00:00');
        SubscriptionService::process(); // tagihan dibuat

        $this->travelTo('2026-12-02 10:00:00'); // lewat berakhir
        SubscriptionService::process();
        $this->assertSame(SubscriptionStatus::PastDue, $this->subscription->fresh()->status);
        $this->assertSame('2026-12-07', $this->subscription->fresh()->grace_until->toDateString());
        $this->assertFalse($this->tenant->fresh()->isSuspended());

        $this->travelTo('2026-12-09 10:00:00'); // lewat masa tenggang
        SubscriptionService::process();
        $this->assertTrue($this->tenant->fresh()->isSuspended());

        $invoice = SubscriptionInvoice::withoutTenancy()->where('status', 'unpaid')->first();
        SubscriptionService::markPaid($invoice);

        $this->assertSame(TenantStatus::Active, $this->tenant->fresh()->status);
        $this->assertSame(SubscriptionStatus::Active, $this->subscription->fresh()->status);
        $this->assertSame('2026-12-31', $this->subscription->fresh()->ends_at->toDateString());
    }

    public function test_pengingat_hanya_sekali(): void
    {
        $this->travelTo('2026-11-25 10:00:00');
        SubscriptionService::generateInvoices();

        $this->assertSame(0, SubscriptionService::sendReminders()); // jatuh tempo 5 hari lagi
        $this->travelTo('2026-11-28 10:00:00');
        $this->assertSame(1, SubscriptionService::sendReminders());
        $this->assertSame(0, SubscriptionService::sendReminders());
    }

    public function test_halaman_panel_owner_dan_platform(): void
    {
        $this->travelTo('2026-11-25 10:00:00');
        SubscriptionService::generateInvoices();
        $number = SubscriptionInvoice::withoutTenancy()->value('number');

        $admin = User::factory()->create(['is_super_admin' => true]);
        $this->actingAs($admin)->get('/platform/subscription-invoices')->assertOk()->assertSee($number);

        $owner = User::where('email', 'owner@villa.test')->first();
        $this->actingAs($owner)->get('/admin/'.$this->tenant->slug.'/subscription-invoices')->assertOk()->assertSee($number);
    }
}
