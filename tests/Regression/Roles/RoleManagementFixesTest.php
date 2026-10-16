<?php

namespace Tests\Regression\Roles;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsTenant;
use Tests\TestCase;

class RoleManagementFixesTest extends TestCase
{
    use ActsAsTenant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenant();
        $this->setUpBranch();
        $this->seed(PermissionSeeder::class);
    }

    public function test_same_named_roles_in_two_tenants_no_longer_cross_assign(): void
    {
        $other = Tenant::factory()->create();
        $foreignRole = Role::factory()->forTenant($other->id)->create(['name' => 'Receptionist']);
        $foreignRole->givePermissionTo('billing.cancel');
        $ownRole = Role::factory()->forTenant($this->tenant->id)->create(['name' => 'Receptionist']);
        $ownRole->givePermissionTo('clients.view');
        $owner = User::factory()->for($this->tenant)->create();
        $owner->assignRole('Owner');
        $this->assignToBranch($owner);

        $this->actingAs($owner)->postToTenant('/users', [
            'name' => 'Desk',
            'username' => 'desk',
            'password' => 'password123',
            'roles' => ['Receptionist'],
            'branch_ids' => [$this->branch->id],
        ]);

        $user = User::where('username', 'desk')->first();
        $this->assertTrue($user->hasRole($ownRole));
        $this->assertFalse($user->hasRole($foreignRole));
        $this->assertFalse($user->can('billing.cancel'));
    }

    public function test_reseeding_permissions_no_longer_overwrites_edited_built_in_roles(): void
    {
        $stylist = Role::where('name', 'Stylist')->first();
        $stylist->syncPermissions(['appointments.view', 'services.view']);

        $this->seed(PermissionSeeder::class);

        $this->assertEqualsCanonicalizing(['appointments.view', 'services.view'], $stylist->fresh()->permissions->pluck('name')->all());
    }

    public function test_reseeding_still_gives_owner_every_permission(): void
    {
        $owner = Role::where('name', 'Owner')->first();
        $owner->syncPermissions(['staff.view']);

        $this->seed(PermissionSeeder::class);

        $this->assertTrue($owner->fresh()->hasPermissionTo('roles.create'));
        $this->assertTrue($owner->fresh()->hasPermissionTo('billing.cancel'));
    }

    public function test_editing_a_role_no_longer_drops_permissions_the_editor_cannot_see(): void
    {
        $editorRole = Role::factory()->forTenant($this->tenant->id)->create(['name' => 'Role Editor']);
        $editorRole->givePermissionTo(['roles.view', 'roles.edit', 'clients.view']);
        $editor = User::factory()->for($this->tenant)->create();
        $editor->assignRole('Role Editor');
        $target = Role::factory()->forTenant($this->tenant->id)->create(['name' => 'Cashier']);
        $target->givePermissionTo(['clients.view', 'billing.cancel']);

        $this->actingAs($editor)->putToTenant("/roles/{$target->id}", [
            'name' => 'Cashier',
            'permissions' => ['clients.view'],
        ])->assertSessionHasNoErrors();

        $this->assertTrue($target->fresh()->hasPermissionTo('billing.cancel'));
    }

    public function test_role_name_must_be_unique_within_the_tenant_but_may_keep_its_own_name_on_update(): void
    {
        $owner = User::factory()->for($this->tenant)->create();
        $owner->assignRole('Owner');
        $role = Role::factory()->forTenant($this->tenant->id)->create(['name' => 'Cashier']);

        $this->actingAs($owner)->putToTenant("/roles/{$role->id}", [
            'name' => 'Cashier',
            'permissions' => ['billing.view'],
        ])->assertSessionHasNoErrors();

        $this->actingAs($owner)->postToTenant('/roles', [
            'name' => 'Cashier',
            'permissions' => ['billing.view'],
        ])->assertSessionHasErrors('name');
    }
}
