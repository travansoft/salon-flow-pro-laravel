<?php

namespace Tests\Feature\Roles;

use App\Models\Branch;
use App\Models\PlatformAdmin;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsSuperAdmin;
use Tests\TestCase;

class SuperAdminRolesTest extends TestCase
{
    use ActsAsSuperAdmin, RefreshDatabase;

    private PlatformAdmin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpMainDomain();
        $this->seed(PermissionSeeder::class);
        $this->admin = PlatformAdmin::factory()->create();
    }

    public function test_super_admin_can_list_system_roles_but_not_tenant_roles(): void
    {
        $tenant = Tenant::factory()->create();
        Role::factory()->forTenant($tenant->id)->create(['name' => 'Tenant Only Role']);

        $response = $this->actingAs($this->admin, 'super_admin')->getFromSuperAdmin('/roles');

        $response->assertOk()->assertSee('ExecutiveViewer')->assertDontSee('Tenant Only Role');
    }

    public function test_super_admin_can_create_a_system_role(): void
    {
        $response = $this->actingAs($this->admin, 'super_admin')->postToSuperAdmin('/roles', [
            'name' => 'Auditor',
            'description' => 'Reads finance',
            'permissions' => ['expenses.view', 'billing.view'],
        ]);

        $response->assertRedirect();
        $role = Role::where('name', 'Auditor')->first();
        $this->assertNull($role->tenant_id);
        $this->assertEqualsCanonicalizing(['expenses.view', 'billing.view'], $role->permissions->pluck('name')->all());
        $this->assertDatabaseHas('super_admin_activity_logs', ['action' => 'role.created']);
    }

    public function test_super_admin_can_change_permissions_of_a_built_in_role_but_not_rename_it(): void
    {
        $role = Role::where('name', 'Stylist')->first();

        $this->actingAs($this->admin, 'super_admin')->putToSuperAdmin("/roles/{$role->id}", [
            'name' => 'Renamed',
            'permissions' => ['appointments.view', 'services.view'],
        ])->assertRedirect();

        $role->refresh();
        $this->assertSame('Stylist', $role->name);
        $this->assertEqualsCanonicalizing(['appointments.view', 'services.view'], $role->permissions->pluck('name')->all());
    }

    public function test_built_in_role_cannot_be_deleted(): void
    {
        $role = Role::where('name', 'Owner')->first();

        $this->actingAs($this->admin, 'super_admin')->deleteFromSuperAdmin("/roles/{$role->id}")->assertSessionHasErrors('role');

        $this->assertDatabaseHas('roles', ['id' => $role->id]);
    }

    public function test_unused_custom_system_role_can_be_deleted(): void
    {
        $role = Role::factory()->create(['name' => 'Auditor']);

        $this->actingAs($this->admin, 'super_admin')->deleteFromSuperAdmin("/roles/{$role->id}")->assertRedirect();

        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
    }

    public function test_super_admin_cannot_edit_a_tenant_custom_role_through_the_system_screen(): void
    {
        $tenant = Tenant::factory()->create();
        $role = Role::factory()->forTenant($tenant->id)->create();

        $this->actingAs($this->admin, 'super_admin')->getFromSuperAdmin("/roles/{$role->id}/edit")->assertNotFound();
    }

    public function test_guest_is_redirected_from_super_admin_roles(): void
    {
        $this->getFromSuperAdmin('/roles')->assertRedirect();
    }

    public function test_tenant_user_form_in_super_admin_offers_system_and_that_tenants_roles_only(): void
    {
        $tenant = Tenant::factory()->create();
        $other = Tenant::factory()->create();
        Role::factory()->forTenant($tenant->id)->create(['name' => 'Mine Role']);
        Role::factory()->forTenant($other->id)->create(['name' => 'Theirs Role']);

        $response = $this->actingAs($this->admin, 'super_admin')->getFromSuperAdmin("/tenants/{$tenant->id}/users/create");

        $response->assertOk()->assertSee('Mine Role')->assertSee('ExecutiveViewer')->assertDontSee('Theirs Role');
    }

    public function test_super_admin_cannot_assign_a_roles_from_another_tenant_to_a_user(): void
    {
        $tenant = Tenant::factory()->create();
        $other = Tenant::factory()->create();
        Role::factory()->forTenant($other->id)->create(['name' => 'Theirs Role']);
        $branch = Branch::factory()->for($tenant)->create();

        $response = $this->actingAs($this->admin, 'super_admin')->postToSuperAdmin("/tenants/{$tenant->id}/users", [
            'name' => 'Jane',
            'username' => 'jane',
            'password' => 'secret',
            'roles' => ['Theirs Role'],
            'branch_ids' => [$branch->id],
        ]);

        $response->assertSessionHasErrors('roles.0');
        $this->assertSame(0, User::withoutGlobalScopes()->where('username', 'jane')->count());
    }
}
