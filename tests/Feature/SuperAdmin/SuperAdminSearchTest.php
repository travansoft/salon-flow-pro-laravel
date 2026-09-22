<?php

namespace Tests\Feature\SuperAdmin;

use App\Models\PlatformAdmin;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsSuperAdmin;
use Tests\TestCase;

class SuperAdminSearchTest extends TestCase
{
    use ActsAsSuperAdmin, RefreshDatabase;

    private PlatformAdmin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpMainDomain();
        $this->admin = PlatformAdmin::factory()->create();
    }

    public function test_tenants_index_can_be_filtered_by_search_term(): void
    {
        Tenant::factory()->create(['name' => 'Glow Studio', 'slug' => 'glow-studio', 'subdomain' => 'glow-studio']);
        Tenant::factory()->create(['name' => 'Shine Salon', 'slug' => 'shine-salon', 'subdomain' => 'shine-salon']);

        $response = $this->actingAs($this->admin, 'super_admin')->getFromSuperAdmin('/tenants?search=glow');

        $response->assertOk();
        $response->assertSee('Glow Studio');
        $response->assertDontSee('Shine Salon');
    }

    public function test_tenant_users_index_can_be_filtered_by_search_term(): void
    {
        $tenant = Tenant::factory()->create();
        User::factory()->for($tenant)->create(['name' => 'Priya Owner', 'username' => 'priya']);
        User::factory()->for($tenant)->create(['name' => 'Anita Stylist', 'username' => 'anita']);

        $response = $this->actingAs($this->admin, 'super_admin')->getFromSuperAdmin("/tenants/{$tenant->id}/users?search=priya");

        $response->assertOk();
        $response->assertSee('Priya Owner');
        $response->assertDontSee('Anita Stylist');
    }
}
