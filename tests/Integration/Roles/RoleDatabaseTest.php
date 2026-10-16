<?php

namespace Tests\Integration\Roles;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Repositories\Contracts\RoleRepositoryInterface;
use App\Services\RoleService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleDatabaseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    public function test_seeder_creates_executive_viewer_with_view_only_permissions(): void
    {
        $role = Role::where('name', 'ExecutiveViewer')->first();

        $this->assertNotNull($role);
        $this->assertNull($role->tenant_id);
        $this->assertTrue($role->hasPermissionTo('dashboard.view'));
        $this->assertFalse($role->hasPermissionTo('billing.create'));
        $this->assertFalse($role->hasPermissionTo('users.view'));
    }

    public function test_seeder_gives_user_and_role_management_only_to_owner(): void
    {
        $this->assertTrue(Role::where('name', 'Owner')->first()->hasPermissionTo('roles.create'));
        $this->assertFalse(Role::where('name', 'Manager')->first()->hasPermissionTo('roles.view'));
        $this->assertFalse(Role::where('name', 'Manager')->first()->hasPermissionTo('users.create'));
    }

    public function test_repository_separates_system_and_tenant_roles(): void
    {
        $tenant = Tenant::factory()->create();
        $other = Tenant::factory()->create();
        Role::factory()->forTenant($tenant->id)->create(['name' => 'Mine']);
        Role::factory()->forTenant($other->id)->create(['name' => 'Theirs']);
        $repository = app(RoleRepositoryInterface::class);

        $this->assertSame(['Mine'], $repository->getOwnedByTenant($tenant->id)->pluck('name')->all());
        $this->assertTrue($repository->getSystemRoles()->pluck('name')->contains('Owner'));
        $this->assertFalse($repository->getSystemRoles()->pluck('name')->contains('Mine'));
        $assignable = $repository->getAssignableByTenant($tenant->id)->pluck('name');
        $this->assertTrue($assignable->contains('Mine'));
        $this->assertTrue($assignable->contains('Stylist'));
        $this->assertFalse($assignable->contains('Theirs'));
    }

    public function test_deleting_a_tenant_removes_its_custom_roles(): void
    {
        $tenant = Tenant::factory()->create();
        $role = Role::factory()->forTenant($tenant->id)->create();

        $tenant->delete();

        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
    }

    public function test_role_permissions_are_persisted_and_checked_by_the_acl(): void
    {
        $tenant = Tenant::factory()->create();
        $role = app(RoleService::class)->create($tenant, [
            'name' => 'Cashier',
            'permissions' => ['billing.view', 'billing.create'],
        ]);
        $user = User::factory()->for($tenant)->create();
        $user->assignRole($role);

        $this->assertDatabaseHas('role_has_permissions', ['role_id' => $role->id]);
        $this->assertTrue($user->can('billing.create'));
        $this->assertFalse($user->can('billing.cancel'));
    }

    public function test_updating_role_permissions_takes_effect_for_existing_users(): void
    {
        $tenant = Tenant::factory()->create();
        $role = app(RoleService::class)->create($tenant, ['name' => 'Cashier', 'permissions' => ['billing.view']]);
        $user = User::factory()->for($tenant)->create();
        $user->assignRole($role);

        app(RoleService::class)->update($role, ['name' => 'Cashier', 'permissions' => ['billing.view', 'billing.create']]);

        $this->assertTrue($user->fresh()->can('billing.create'));
    }

    public function test_resolve_for_tenant_prefers_only_roles_the_tenant_may_use(): void
    {
        $tenant = Tenant::factory()->create();
        $other = Tenant::factory()->create();
        $mine = Role::factory()->forTenant($tenant->id)->create(['name' => 'Receptionist']);
        Role::factory()->forTenant($other->id)->create(['name' => 'Receptionist']);

        $resolved = app(RoleService::class)->resolveForTenant(['Receptionist', 'Stylist'], $tenant->id);

        $this->assertEqualsCanonicalizing([$mine->id, Role::where('name', 'Stylist')->value('id')], $resolved->pluck('id')->all());
    }
}
