<?php

namespace Tests\Feature\Roles;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsTenant;
use Tests\TestCase;

class ManageRolesTest extends TestCase
{
    use ActsAsTenant, RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenant();
        $this->seed(PermissionSeeder::class);

        $this->owner = User::factory()->for($this->tenant)->create();
        $this->owner->assignRole('Owner');
    }

    public function test_owner_can_create_a_custom_role_with_chosen_permissions(): void
    {
        $response = $this->actingAs($this->owner)->postToTenant('/roles', [
            'name' => 'Receptionist',
            'description' => 'Front of house',
            'permissions' => ['clients.view', 'clients.create'],
        ]);

        $role = Role::where('name', 'Receptionist')->first();
        $response->assertRedirect();
        $this->assertSame($this->tenant->id, $role->tenant_id);
        $this->assertEqualsCanonicalizing(['clients.view', 'clients.create'], $role->permissions->pluck('name')->all());
    }

    public function test_custom_role_is_listed_in_the_tenant_role_index(): void
    {
        Role::factory()->forTenant($this->tenant->id)->create(['name' => 'Receptionist']);

        $response = $this->actingAs($this->owner)->getFromTenant('/roles');

        $response->assertOk()->assertSee('Receptionist')->assertSee('Director');
    }

    public function test_other_tenants_custom_roles_are_not_listed(): void
    {
        $otherTenant = Tenant::factory()->create();
        Role::factory()->forTenant($otherTenant->id)->create(['name' => 'Rival Secret Role']);

        $response = $this->actingAs($this->owner)->getFromTenant('/roles');

        $response->assertOk()->assertDontSee('Rival Secret Role');
    }

    public function test_validation_requires_name_and_at_least_one_permission(): void
    {
        $response = $this->actingAs($this->owner)->postToTenant('/roles', []);

        $response->assertSessionHasErrors(['name', 'permissions']);
    }

    public function test_validation_rejects_unknown_permissions(): void
    {
        $response = $this->actingAs($this->owner)->postToTenant('/roles', [
            'name' => 'Sneaky',
            'permissions' => ['not.a.permission'],
        ]);

        $response->assertSessionHasErrors('permissions.0');
    }

    public function test_validation_rejects_a_name_used_by_a_standard_role(): void
    {
        $response = $this->actingAs($this->owner)->postToTenant('/roles', [
            'name' => 'Manager',
            'permissions' => ['staff.view'],
        ]);

        $response->assertSessionHasErrors('name');
    }

    public function test_two_tenants_can_each_have_a_role_with_the_same_name(): void
    {
        $otherTenant = Tenant::factory()->create();
        Role::factory()->forTenant($otherTenant->id)->create(['name' => 'Receptionist']);

        $response = $this->actingAs($this->owner)->postToTenant('/roles', [
            'name' => 'Receptionist',
            'permissions' => ['clients.view'],
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertSame(2, Role::where('name', 'Receptionist')->count());
    }

    public function test_user_cannot_grant_a_permission_they_do_not_hold(): void
    {
        $limitedRole = Role::factory()->forTenant($this->tenant->id)->create(['name' => 'Role Builder']);
        $limitedRole->givePermissionTo(['roles.view', 'roles.create', 'clients.view']);
        $builder = User::factory()->for($this->tenant)->create();
        $builder->assignRole('Role Builder');

        $response = $this->actingAs($builder)->postToTenant('/roles', [
            'name' => 'Escalated',
            'permissions' => ['clients.view', 'billing.cancel'],
        ]);

        $response->assertSessionHasErrors('permissions.1');
        $this->assertDatabaseMissing('roles', ['name' => 'Escalated']);
    }

    public function test_owner_can_update_a_custom_role(): void
    {
        $role = Role::factory()->forTenant($this->tenant->id)->create(['name' => 'Receptionist']);
        $role->givePermissionTo('clients.view');

        $response = $this->actingAs($this->owner)->putToTenant("/roles/{$role->id}", [
            'name' => 'Senior Receptionist',
            'permissions' => ['clients.view', 'clients.edit'],
        ]);

        $response->assertRedirect();
        $role->refresh();
        $this->assertSame('Senior Receptionist', $role->name);
        $this->assertEqualsCanonicalizing(['clients.view', 'clients.edit'], $role->permissions->pluck('name')->all());
    }

    public function test_owner_cannot_edit_a_standard_role(): void
    {
        $standard = Role::where('name', 'Stylist')->first();

        $this->actingAs($this->owner)->getFromTenant("/roles/{$standard->id}/edit")->assertNotFound();
        $this->actingAs($this->owner)->putToTenant("/roles/{$standard->id}", [
            'name' => 'Stylist',
            'permissions' => ['billing.cancel'],
        ])->assertNotFound();
    }

    public function test_owner_cannot_edit_or_delete_another_tenants_role(): void
    {
        $otherTenant = Tenant::factory()->create();
        $foreign = Role::factory()->forTenant($otherTenant->id)->create();

        $this->actingAs($this->owner)->getFromTenant("/roles/{$foreign->id}")->assertNotFound();
        $this->actingAs($this->owner)->getFromTenant("/roles/{$foreign->id}/edit")->assertNotFound();
        $this->actingAs($this->owner)->deleteFromTenant("/roles/{$foreign->id}")->assertNotFound();
        $this->assertDatabaseHas('roles', ['id' => $foreign->id]);
    }

    public function test_owner_can_delete_an_unused_custom_role(): void
    {
        $role = Role::factory()->forTenant($this->tenant->id)->create();

        $response = $this->actingAs($this->owner)->deleteFromTenant("/roles/{$role->id}");

        $response->assertRedirect();
        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
    }

    public function test_role_assigned_to_a_user_cannot_be_deleted(): void
    {
        $role = Role::factory()->forTenant($this->tenant->id)->create(['name' => 'Receptionist']);
        $member = User::factory()->for($this->tenant)->create();
        $member->assignRole($role);

        $response = $this->actingAs($this->owner)->deleteFromTenant("/roles/{$role->id}");

        $response->assertSessionHasErrors('role');
        $this->assertDatabaseHas('roles', ['id' => $role->id]);
    }
}
