<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PanelAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_tamu_diarahkan_ke_halaman_login_panel(): void
    {
        $this->get('/platform')->assertRedirect();
        $this->get('/admin')->assertRedirect();
    }

    public function test_panel_platform_hanya_untuk_super_admin(): void
    {
        $owner = User::factory()->create();
        Tenant::factory()->create()->users()->attach($owner, ['role' => 'owner']);

        $this->actingAs($owner)->get('/platform')->assertForbidden();

        $admin = User::factory()->create(['is_super_admin' => true]);

        $this->actingAs($admin)->get('/platform')->assertOk();
    }

    public function test_owner_bisa_membuka_panel_tenant_miliknya(): void
    {
        $owner = User::factory()->create();
        $tenant = Tenant::factory()->create();
        $tenant->users()->attach($owner, ['role' => 'owner']);

        $this->actingAs($owner)->get('/admin/'.$tenant->slug)->assertOk();
    }

    public function test_owner_tidak_bisa_membuka_panel_tenant_lain(): void
    {
        $owner = User::factory()->create();
        Tenant::factory()->create()->users()->attach($owner, ['role' => 'owner']);
        $other = Tenant::factory()->create();

        $status = $this->actingAs($owner)->get('/admin/'.$other->slug)->getStatusCode();

        $this->assertContains($status, [403, 404]);
    }

    public function test_pengguna_tanpa_tenant_tidak_bisa_masuk_panel_admin(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/admin')->assertForbidden();
    }
}
