<?php

namespace Tests\Feature;

use App\Enums\SubscriptionStatus;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PlanAndSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_plan_seeder_membuat_tiga_paket_berdasarkan_kamar(): void
    {
        $this->seed(PlanSeeder::class);

        $plans = Plan::orderBy('max_rooms')->get(['slug', 'price', 'max_rooms']);

        $this->assertSame(['starter', 'growth', 'business'], $plans->pluck('slug')->all());
        $this->assertSame([10, 20, 30], $plans->pluck('max_rooms')->all());
        $this->assertSame([150000, 250000, 350000], $plans->pluck('price')->all());
    }

    public function test_plan_seeder_aman_dijalankan_berulang(): void
    {
        $this->seed(PlanSeeder::class);
        $this->seed(PlanSeeder::class);

        $this->assertSame(3, Plan::count());
    }

    public function test_tenant_baru_otomatis_punya_pengaturan_default(): void
    {
        $tenant = Tenant::factory()->create();

        $this->assertNotNull($tenant->settings);
        $this->assertSame('full', $tenant->settings->payment_mode);
        $this->assertSame('link', $tenant->settings->wa_driver);
    }

    public function test_kredensial_gateway_tersimpan_terenkripsi(): void
    {
        $tenant = Tenant::factory()->create();
        $tenant->settings->update(['gateway_credentials' => ['api_key' => 'rahasia']]);

        $raw = DB::table('tenant_settings')->where('tenant_id', $tenant->id)->value('gateway_credentials');

        $this->assertStringNotContainsString('rahasia', (string) $raw);
        $this->assertSame('rahasia', $tenant->settings->fresh()->gateway_credentials['api_key']);
    }

    public function test_batas_kamar_mengikuti_paket_langganan_aktif(): void
    {
        $tenant = Tenant::factory()->create();
        $plan = Plan::factory()->create(['max_rooms' => 20]);

        Subscription::factory()->create(['tenant_id' => $tenant->id, 'plan_id' => $plan->id]);

        $this->assertSame(20, $tenant->roomLimit());
    }

    public function test_tanpa_langganan_batas_kamar_nol(): void
    {
        $this->assertSame(0, Tenant::factory()->create()->roomLimit());
    }

    public function test_langganan_kedaluwarsa_atau_dibatalkan_tidak_dihitung(): void
    {
        $plan = Plan::factory()->create(['max_rooms' => 10]);

        $expired = Tenant::factory()->create();
        Subscription::factory()->create([
            'tenant_id' => $expired->id,
            'plan_id' => $plan->id,
            'ends_at' => now()->subDay()->toDateString(),
        ]);

        $canceled = Tenant::factory()->create();
        Subscription::factory()->create([
            'tenant_id' => $canceled->id,
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::Canceled->value,
        ]);

        $this->assertSame(0, $expired->roomLimit());
        $this->assertSame(0, $canceled->roomLimit());
    }
}
