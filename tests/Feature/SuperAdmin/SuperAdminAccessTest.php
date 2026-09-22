<?php

namespace Tests\Feature\SuperAdmin;

use App\Models\PlatformAdmin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsSuperAdmin;
use Tests\Concerns\ActsAsTenant;
use Tests\TestCase;

class SuperAdminAccessTest extends TestCase
{
    use ActsAsSuperAdmin, ActsAsTenant, RefreshDatabase;

    public function test_guest_is_redirected_to_super_admin_login(): void
    {
        $this->setUpMainDomain();

        $response = $this->getFromSuperAdmin('/tenants');

        $response->assertRedirect($this->superAdminUrl('/login'));
    }

    public function test_authenticated_platform_admin_can_access_dashboard(): void
    {
        $this->setUpMainDomain();
        $admin = PlatformAdmin::factory()->create();

        $response = $this->actingAs($admin, 'super_admin')->getFromSuperAdmin('/');

        $response->assertOk();
    }

    public function test_tenant_scoped_user_session_cannot_access_super_admin_panel(): void
    {
        $this->setUpTenant();
        $user = User::factory()->for($this->tenant)->create();

        $response = $this->actingAs($user)->getFromSuperAdmin('/tenants');

        $response->assertRedirect($this->superAdminUrl('/login'));
    }

    public function test_non_admin_subdomain_returns_404_for_admin_only_route(): void
    {
        $this->setUpMainDomain();

        $response = $this->get('http://random.salonflow.test/tenants');

        $response->assertNotFound();
    }
}
