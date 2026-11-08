<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PanelLocaleTimezoneTest extends TestCase
{
    use RefreshDatabase;

    public function test_panel_platform_berbahasa_indonesia_walau_app_locale_en(): void
    {
        config(['app.locale' => 'en', 'booking.default_locale' => 'en', 'booking.panel_locale' => 'id']);

        $admin = User::factory()->create(['is_super_admin' => true]);

        $this->actingAs($admin)->get('/platform')->assertOk()->assertSee('Langganan');
    }

    public function test_panel_owner_mengikuti_bahasa_default_tenant(): void
    {
        config(['app.locale' => 'en', 'booking.default_locale' => 'en', 'booking.panel_locale' => 'en']);

        $tenant = Tenant::factory()->create(['default_locale' => 'id']);
        $owner = User::factory()->create();
        $tenant->users()->attach($owner, ['role' => 'owner']);

        $this->actingAs($owner)->get('/admin/'.$tenant->slug.'/subscription-invoices')->assertOk()->assertSee('Tagihan');
    }

    public function test_nama_zona_waktu_tenant_punya_default_wita(): void
    {
        $this->assertSame('Asia/Makassar', (new Tenant(['timezone' => null]))->timezoneName());
        $this->assertSame('Asia/Jakarta', (new Tenant(['timezone' => 'Asia/Jakarta']))->timezoneName());
    }
}
