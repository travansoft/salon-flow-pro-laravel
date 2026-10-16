<?php

namespace Tests\Feature\Roles;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsTenant;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use ActsAsTenant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenant();
        $this->seed(PermissionSeeder::class);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->for($this->tenant)->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_owner_can_access_role_index_create_and_show(): void
    {
        $owner = $this->userWithRole('Owner');
        $role = Role::where('name', 'Stylist')->first();

        $this->actingAs($owner)->getFromTenant('/roles')->assertOk();
        $this->actingAs($owner)->getFromTenant('/roles/create')->assertOk();
        $this->actingAs($owner)->getFromTenant("/roles/{$role->id}")->assertOk();
    }

    public function test_manager_cannot_access_role_index(): void
    {
        $manager = $this->userWithRole('Manager');

        $this->actingAs($manager)->getFromTenant('/roles')->assertForbidden();
    }

    public function test_front_desk_cannot_create_a_role(): void
    {
        $frontDesk = $this->userWithRole('FrontDesk');

        $this->actingAs($frontDesk)->getFromTenant('/roles/create')->assertForbidden();
        $this->actingAs($frontDesk)->postToTenant('/roles', ['name' => 'X', 'permissions' => ['staff.view']])->assertForbidden();
    }

    public function test_executive_viewer_cannot_open_role_screens(): void
    {
        $viewer = $this->userWithRole('ExecutiveViewer');

        $this->actingAs($viewer)->getFromTenant('/roles')->assertForbidden();
        $this->actingAs($viewer)->getFromTenant('/users')->assertForbidden();
    }

    public function test_editor_without_delete_permission_cannot_delete_a_role(): void
    {
        $editorRole = Role::factory()->forTenant($this->tenant->id)->create(['name' => 'Role Editor']);
        $editorRole->givePermissionTo(['roles.view', 'roles.edit']);
        $editor = $this->userWithRole('Role Editor');
        $target = Role::factory()->forTenant($this->tenant->id)->create();

        $response = $this->actingAs($editor)->deleteFromTenant("/roles/{$target->id}");

        $response->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->getFromTenant('/roles')->assertRedirect('/login');
        $this->getFromTenant('/users')->assertRedirect('/login');
    }
}
