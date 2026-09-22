<?php

namespace Tests\Regression\SuperAdmin;

use App\Models\PlatformAdmin;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsSuperAdmin;
use Tests\TestCase;

class TenantScopeBypassResolvesBeforeRouteBindingFixTest extends TestCase
{
    use ActsAsSuperAdmin, RefreshDatabase;

    /**
     * Bug: TenantContext::bypass() was only called from the super_admin.only
     * route middleware, which (as ordinary route middleware) runs after
     * Laravel's SubstituteBindings middleware. Any super-admin route with an
     * implicit binding on the tenant-scoped User model (e.g.
     * PUT /tenants/{tenant}/users/{tenantUser}) resolved that binding before
     * the bypass was active, so the tenant-scoped query threw
     * NoTenantContextException instead of finding the record. Fixed by
     * moving the bypass into ResolveTenant, which is prepended to the web
     * middleware group and so runs before route model binding.
     */
    public function test_route_bound_tenant_user_resolves_under_super_admin_panel(): void
    {
        $this->setUpMainDomain();
        $admin = PlatformAdmin::factory()->create();
        $tenant = Tenant::factory()->create();
        $user = User::factory()->for($tenant)->create();

        $response = $this->actingAs($admin, 'super_admin')->putToSuperAdmin("/tenants/{$tenant->id}/users/{$user->id}", [
            'name' => $user->name,
            'username' => $user->username,
        ]);

        $response->assertRedirect();
    }
}
