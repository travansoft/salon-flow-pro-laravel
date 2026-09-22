<?php

namespace Tests\Regression\SuperAdmin;

use App\Models\PlatformAdmin;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsSuperAdmin;
use Tests\TestCase;

class TenantUserRouteScopedToItsOwnTenantFixTest extends TestCase
{
    use ActsAsSuperAdmin, RefreshDatabase;

    private PlatformAdmin $admin;

    private Tenant $tenantA;

    private Tenant $tenantB;

    private User $userFromTenantB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpMainDomain();
        $this->admin = PlatformAdmin::factory()->create();
        $this->tenantA = Tenant::factory()->create();
        $this->tenantB = Tenant::factory()->create();
        $this->userFromTenantB = User::factory()->for($this->tenantB)->create();
    }

    /**
     * Bug: TenantUsersController's edit/update/destroy and
     * TenantUserImpersonationController's store accepted {tenant} and
     * {tenantUser} as independent route-bound parameters without checking
     * that the user actually belongs to that tenant. A super admin browsing
     * tenant A could substitute a user id belonging to tenant B in the URL
     * and edit, disable, or impersonate that user under tenant A's context,
     * producing misleading activity-log entries and a broken redirect after
     * impersonation. Fixed by asserting $tenantUser->tenant_id === $tenant->id
     * (404) at the top of every affected action.
     */
    public function test_editing_a_user_under_the_wrong_tenant_is_not_found(): void
    {
        $response = $this->actingAs($this->admin, 'super_admin')
            ->getFromSuperAdmin("/tenants/{$this->tenantA->id}/users/{$this->userFromTenantB->id}/edit");

        $response->assertNotFound();
    }

    public function test_updating_a_user_under_the_wrong_tenant_is_not_found(): void
    {
        $response = $this->actingAs($this->admin, 'super_admin')
            ->putToSuperAdmin("/tenants/{$this->tenantA->id}/users/{$this->userFromTenantB->id}", [
                'name' => 'Hijacked',
                'username' => $this->userFromTenantB->username,
            ]);

        $response->assertNotFound();
        $this->assertNotSame('Hijacked', $this->userFromTenantB->refresh()->name);
    }

    public function test_toggling_login_for_a_user_under_the_wrong_tenant_is_not_found(): void
    {
        $response = $this->actingAs($this->admin, 'super_admin')
            ->putToSuperAdmin("/tenants/{$this->tenantA->id}/users/{$this->userFromTenantB->id}/toggle-login");

        $response->assertNotFound();
        $this->assertTrue($this->userFromTenantB->refresh()->isLoginEnabled());
    }

    public function test_impersonating_a_user_under_the_wrong_tenant_is_not_found(): void
    {
        $response = $this->actingAs($this->admin, 'super_admin')
            ->postToSuperAdmin("/tenants/{$this->tenantA->id}/users/{$this->userFromTenantB->id}/impersonate");

        $response->assertNotFound();
        $this->assertGuest('web');
    }
}
