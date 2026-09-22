<?php

namespace Tests\Feature\SuperAdmin;

use App\Models\PlatformAdmin;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsSuperAdmin;
use Tests\TestCase;

class TenantUserImpersonationTest extends TestCase
{
    use ActsAsSuperAdmin, RefreshDatabase;

    private PlatformAdmin $admin;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpMainDomain();
        $this->admin = PlatformAdmin::factory()->create();
        $this->tenant = Tenant::factory()->create(['subdomain' => 'mejora']);
    }

    private function tenantUrl(string $uri): string
    {
        return "http://{$this->tenant->subdomain}.salonflow.test".$uri;
    }

    public function test_super_admin_can_start_impersonating_an_enabled_tenant_user(): void
    {
        $user = User::factory()->for($this->tenant)->create();

        $response = $this->actingAs($this->admin, 'super_admin')
            ->postToSuperAdmin("/tenants/{$this->tenant->id}/users/{$user->id}/impersonate");

        $response->assertRedirect("http://salonflow.test/{$this->tenant->slug}/dashboard");
        $this->assertAuthenticatedAs($user, 'web');
    }

    public function test_impersonating_a_disabled_user_is_forbidden(): void
    {
        $user = User::factory()->for($this->tenant)->create(['disabled_at' => now()]);

        $response = $this->actingAs($this->admin, 'super_admin')
            ->postToSuperAdmin("/tenants/{$this->tenant->id}/users/{$user->id}/impersonate");

        $response->assertForbidden();
    }

    public function test_impersonation_start_is_logged(): void
    {
        $user = User::factory()->for($this->tenant)->create();

        $this->actingAs($this->admin, 'super_admin')
            ->postToSuperAdmin("/tenants/{$this->tenant->id}/users/{$user->id}/impersonate");

        $this->assertDatabaseHas('super_admin_activity_logs', [
            'platform_admin_id' => $this->admin->id,
            'action' => 'tenant_user.impersonation_started',
        ]);
    }

    public function test_impersonated_user_can_return_to_the_admin_panel(): void
    {
        $user = User::factory()->for($this->tenant)->create();

        $response = $this->actingAs($user, 'web')
            ->withSession([
                'impersonator_platform_admin_id' => $this->admin->id,
                'impersonator_platform_admin_name' => $this->admin->name,
            ])
            ->delete($this->tenantUrl('/impersonation'));

        $response->assertRedirect('http://salonflow.test/admin');
        $this->assertGuest('web');
        $this->assertDatabaseHas('super_admin_activity_logs', [
            'platform_admin_id' => $this->admin->id,
            'action' => 'tenant_user.impersonation_ended',
        ]);
    }

    public function test_stopping_impersonation_without_an_active_session_returns_404(): void
    {
        $user = User::factory()->for($this->tenant)->create();

        $response = $this->actingAs($user)->delete($this->tenantUrl('/impersonation'));

        $response->assertNotFound();
    }

    public function test_guest_cannot_start_impersonation(): void
    {
        $user = User::factory()->for($this->tenant)->create();

        $response = $this->postToSuperAdmin("/tenants/{$this->tenant->id}/users/{$user->id}/impersonate");

        $response->assertRedirect($this->superAdminUrl('/login'));
    }
}
