<?php

namespace Tests\Feature\SuperAdmin;

use App\Models\PlatformAdmin;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsSuperAdmin;
use Tests\TestCase;

class SuperAdminActivityLogTest extends TestCase
{
    use ActsAsSuperAdmin, RefreshDatabase;

    private PlatformAdmin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpMainDomain();
        $this->admin = PlatformAdmin::factory()->create();
    }

    public function test_creating_a_tenant_is_recorded_in_the_activity_log(): void
    {
        $this->actingAs($this->admin, 'super_admin')->postToSuperAdmin('/tenants', [
            'name' => 'Logged Salon',
            'slug' => 'logged-salon',
            'subdomain' => 'logged-salon',
        ]);

        $this->assertDatabaseHas('super_admin_activity_logs', [
            'platform_admin_id' => $this->admin->id,
            'action' => 'tenant.created',
        ]);
    }

    public function test_deactivating_a_tenant_is_recorded(): void
    {
        $tenant = Tenant::factory()->create(['is_active' => true]);

        $this->actingAs($this->admin, 'super_admin')->deleteFromSuperAdmin("/tenants/{$tenant->id}");

        $this->assertDatabaseHas('super_admin_activity_logs', [
            'action' => 'tenant.deactivated',
            'subject_type' => $tenant->getMorphClass(),
            'subject_id' => $tenant->id,
        ]);
    }

    public function test_super_admin_can_view_the_activity_log(): void
    {
        $response = $this->actingAs($this->admin, 'super_admin')->getFromSuperAdmin('/activity');

        $response->assertOk();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->getFromSuperAdmin('/activity');

        $response->assertRedirect($this->superAdminUrl('/login'));
    }
}
