<?php

namespace Tests\Feature\SuperAdmin;

use App\Models\PlatformAdmin;
use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsSuperAdmin;
use Tests\TestCase;

class ManageTenantUsersTest extends TestCase
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

    public function test_super_admin_can_view_tenant_users_index(): void
    {
        User::factory()->for($this->tenant)->create();

        $response = $this->actingAs($this->admin, 'super_admin')->getFromSuperAdmin("/tenants/{$this->tenant->id}/users");

        $response->assertOk();
    }

    public function test_super_admin_can_create_a_tenant_user_with_a_role(): void
    {
        $response = $this->actingAs($this->admin, 'super_admin')->postToSuperAdmin("/tenants/{$this->tenant->id}/users", [
            'name' => 'Jane Owner',
            'username' => 'jane',
            'password' => 'secret',
            'roles' => ['Owner'],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('users', [
            'tenant_id' => $this->tenant->id,
            'username' => 'jane',
        ]);
        $user = User::withoutGlobalScope(TenantScope::class)->where('username', 'jane')->first();
        $this->assertTrue($user->hasRole('Owner'));
    }

    public function test_username_must_be_unique_within_the_tenant(): void
    {
        User::factory()->for($this->tenant)->create(['username' => 'jane']);

        $response = $this->actingAs($this->admin, 'super_admin')->postToSuperAdmin("/tenants/{$this->tenant->id}/users", [
            'name' => 'Jane Duplicate',
            'username' => 'jane',
            'password' => 'secret',
        ]);

        $response->assertSessionHasErrors('username');
    }

    public function test_same_username_is_allowed_across_different_tenants(): void
    {
        $otherTenant = Tenant::factory()->create();
        User::factory()->for($otherTenant)->create(['username' => 'jane']);

        $response = $this->actingAs($this->admin, 'super_admin')->postToSuperAdmin("/tenants/{$this->tenant->id}/users", [
            'name' => 'Jane Owner',
            'username' => 'jane',
            'password' => 'secret',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('users', ['tenant_id' => $this->tenant->id, 'username' => 'jane']);
    }

    public function test_super_admin_can_update_a_tenant_users_roles(): void
    {
        $user = User::factory()->for($this->tenant)->create();
        $user->assignRole('Stylist');

        $response = $this->actingAs($this->admin, 'super_admin')->putToSuperAdmin("/tenants/{$this->tenant->id}/users/{$user->id}", [
            'name' => $user->name,
            'username' => $user->username,
            'roles' => ['Manager'],
        ]);

        $response->assertRedirect();
        $user->refresh();
        $this->assertTrue($user->hasRole('Manager'));
        $this->assertFalse($user->hasRole('Stylist'));
    }

    public function test_super_admin_can_disable_and_enable_a_tenant_users_login(): void
    {
        $user = User::factory()->for($this->tenant)->create();

        $response = $this->actingAs($this->admin, 'super_admin')->putToSuperAdmin("/tenants/{$this->tenant->id}/users/{$user->id}/toggle-login");
        $response->assertRedirect();
        $this->assertFalse($user->refresh()->isLoginEnabled());

        $response = $this->actingAs($this->admin, 'super_admin')->putToSuperAdmin("/tenants/{$this->tenant->id}/users/{$user->id}/toggle-login");
        $response->assertRedirect();
        $this->assertTrue($user->refresh()->isLoginEnabled());
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->getFromSuperAdmin("/tenants/{$this->tenant->id}/users");

        $response->assertRedirect($this->superAdminUrl('/login'));
    }
}
