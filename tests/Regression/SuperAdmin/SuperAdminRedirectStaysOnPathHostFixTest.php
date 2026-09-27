<?php

namespace Tests\Regression\SuperAdmin;

use App\Models\PlatformAdmin;
use App\Models\Tenant;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsSuperAdmin;
use Tests\TestCase;

class SuperAdminRedirectStaysOnPathHostFixTest extends TestCase
{
    use ActsAsSuperAdmin, RefreshDatabase;

    private PlatformAdmin $admin;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpMainDomain();
        $this->seed(PermissionSeeder::class);
        $this->admin = PlatformAdmin::factory()->create();
        $this->tenant = Tenant::factory()->create();
    }

    /**
     * Bug: super-admin controllers redirected via the plain route() helper,
     * which always resolves to the admin.{mainDomain} subdomain name. A super
     * admin working under the mainDomain/admin path variant (e.g. behind a
     * host that doesn't route the admin subdomain) was redirected to the
     * subdomain after every create/update, landing on a page that doesn't
     * resolve. Fixed by redirecting through SuperAdminUrl::route(), which
     * picks the host variant the request actually arrived on.
     */
    public function test_creating_a_tenant_user_via_the_path_host_redirects_on_the_same_host(): void
    {
        $response = $this->actingAs($this->admin, 'super_admin')
            ->postToSuperAdminByPath("/tenants/{$this->tenant->id}/users", [
                'name' => 'Jane Owner',
                'username' => 'jane',
                'password' => 'secret',
                'roles' => ['Owner'],
            ]);

        $response->assertRedirect($this->superAdminByPathUrl("/tenants/{$this->tenant->id}/users"));
    }
}
