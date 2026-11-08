<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResolveTenantTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['booking.base_domain' => 'localhost']);
    }

    public function test_subdomain_tenant_dikenali(): void
    {
        Tenant::factory()->create(['name' => 'Villa Bunga', 'slug' => 'bunga', 'subdomain' => 'bunga']);

        $this->get('http://bunga.localhost/')
            ->assertOk()
            ->assertSee('Villa Bunga');
    }

    public function test_subdomain_tidak_dikenal_menghasilkan_404(): void
    {
        $this->get('http://tidak-ada.localhost/')->assertNotFound();
    }

    public function test_domain_sendiri_tenant_dikenali(): void
    {
        Tenant::factory()->create(['name' => 'Villa Domain', 'custom_domain' => 'villa-domain.com']);

        $this->get('http://villa-domain.com/')
            ->assertOk()
            ->assertSee('Villa Domain');
    }

    public function test_tenant_suspended_mendapat_503(): void
    {
        Tenant::factory()->suspended()->create(['slug' => 'mati', 'subdomain' => 'mati']);

        $this->get('http://mati.localhost/')->assertStatus(503);
    }

    public function test_host_induk_dan_subdomain_reserved_adalah_konteks_pusat(): void
    {
        $this->get('http://localhost/')->assertOk()->assertSee('Booking Villa');
        $this->get('http://app.localhost/')->assertOk()->assertSee('Booking Villa');
    }
}
