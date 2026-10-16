<?php

namespace Tests\Feature\Users;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsTenant;
use Tests\TestCase;

class ManageUsersTest extends TestCase
{
    use ActsAsTenant, RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenant();
        $this->setUpBranch();
        $this->seed(PermissionSeeder::class);

        $this->owner = User::factory()->for($this->tenant)->create();
        $this->owner->assignRole('Owner');
        $this->assignToBranch($this->owner);
    }

    /** @return array<string, mixed> */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Board Member',
            'username' => 'boardmember',
            'password' => 'password123',
            'roles' => ['Director'],
            'branch_ids' => [$this->branch->id],
        ], $overrides);
    }

    public function test_owner_can_view_user_index_with_seat_usage(): void
    {
        $response = $this->actingAs($this->owner)->getFromTenant('/users');

        $response->assertOk()->assertSee('1 of 5 active users');
    }

    public function test_owner_can_add_a_user_with_the_director_role(): void
    {
        $response = $this->actingAs($this->owner)->postToTenant('/users', $this->validPayload());

        $response->assertRedirect();
        $user = User::where('username', 'boardmember')->first();
        $this->assertSame($this->tenant->id, $user->tenant_id);
        $this->assertTrue($user->hasRole('Director'));
        $this->assertTrue($user->branches->contains($this->branch));
    }

    public function test_owner_can_assign_a_custom_role_of_their_own_tenant(): void
    {
        $role = Role::factory()->forTenant($this->tenant->id)->create(['name' => 'Receptionist']);
        $role->givePermissionTo('clients.view');

        $this->actingAs($this->owner)->postToTenant('/users', $this->validPayload(['roles' => ['Receptionist']]));

        $this->assertTrue(User::where('username', 'boardmember')->first()->hasRole($role));
    }

    public function test_owner_cannot_assign_another_tenants_custom_role(): void
    {
        $otherTenant = Tenant::factory()->create();
        Role::factory()->forTenant($otherTenant->id)->create(['name' => 'Rival Role']);

        $response = $this->actingAs($this->owner)->postToTenant('/users', $this->validPayload(['roles' => ['Rival Role']]));

        $response->assertSessionHasErrors('roles.0');
        $this->assertDatabaseMissing('users', ['username' => 'boardmember']);
    }

    public function test_validation_requires_role_branch_and_password(): void
    {
        $response = $this->actingAs($this->owner)->postToTenant('/users', ['name' => 'X', 'username' => 'x']);

        $response->assertSessionHasErrors(['password', 'roles', 'branch_ids']);
    }

    public function test_adding_a_user_beyond_the_limit_is_rejected(): void
    {
        User::factory()->for($this->tenant)->count(4)->create();

        $response = $this->actingAs($this->owner)->postToTenant('/users', $this->validPayload());

        $response->assertSessionHasErrors('limit');
        $this->assertDatabaseMissing('users', ['username' => 'boardmember']);
    }

    public function test_raised_tenant_limit_allows_more_users(): void
    {
        $this->tenant->update(['max_users' => 6]);
        User::factory()->for($this->tenant)->count(4)->create();

        $this->actingAs($this->owner)->postToTenant('/users', $this->validPayload())->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', ['username' => 'boardmember']);
    }

    public function test_disabling_a_user_frees_a_seat(): void
    {
        $others = User::factory()->for($this->tenant)->count(4)->create();
        $others->first()->update(['disabled_at' => now()]);

        $this->actingAs($this->owner)->postToTenant('/users', $this->validPayload())->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', ['username' => 'boardmember']);
    }

    public function test_owner_can_change_a_users_role(): void
    {
        $member = User::factory()->for($this->tenant)->create(['username' => 'member']);
        $member->assignRole('Stylist');
        $this->assignToBranch($member);

        $response = $this->actingAs($this->owner)->putToTenant("/users/{$member->id}", $this->validPayload([
            'username' => 'member',
            'roles' => ['Director'],
        ]));

        $response->assertRedirect();
        $this->assertTrue($member->fresh()->hasRole('Director'));
        $this->assertFalse($member->fresh()->hasRole('Stylist'));
    }

    public function test_owner_cannot_change_their_own_role(): void
    {
        $this->actingAs($this->owner)->putToTenant("/users/{$this->owner->id}", $this->validPayload([
            'username' => $this->owner->username,
            'password' => null,
            'roles' => ['Stylist'],
        ]));

        $this->assertTrue($this->owner->fresh()->hasRole('Owner'));
    }

    public function test_owner_can_disable_and_enable_another_users_login(): void
    {
        $member = User::factory()->for($this->tenant)->create();

        $this->actingAs($this->owner)->putToTenant("/users/{$member->id}/toggle-login");
        $this->assertNotNull($member->fresh()->disabled_at);

        $this->actingAs($this->owner)->putToTenant("/users/{$member->id}/toggle-login");
        $this->assertNull($member->fresh()->disabled_at);
    }

    public function test_owner_cannot_disable_their_own_login(): void
    {
        $response = $this->actingAs($this->owner)->putToTenant("/users/{$this->owner->id}/toggle-login");

        $response->assertSessionHasErrors('user');
        $this->assertNull($this->owner->fresh()->disabled_at);
    }

    public function test_enabling_a_login_beyond_the_limit_is_rejected(): void
    {
        User::factory()->for($this->tenant)->count(4)->create();
        $disabled = User::factory()->for($this->tenant)->create(['disabled_at' => now()]);

        $response = $this->actingAs($this->owner)->putToTenant("/users/{$disabled->id}/toggle-login");

        $response->assertSessionHasErrors('limit');
        $this->assertNotNull($disabled->fresh()->disabled_at);
    }

    public function test_owner_cannot_edit_a_user_of_another_tenant(): void
    {
        $foreign = User::factory()->create();

        $this->actingAs($this->owner)->getFromTenant("/users/{$foreign->id}/edit")->assertNotFound();
    }

    public function test_manager_cannot_manage_users(): void
    {
        $manager = User::factory()->for($this->tenant)->create();
        $manager->assignRole('Manager');

        $this->actingAs($manager)->getFromTenant('/users')->assertForbidden();
        $this->actingAs($manager)->getFromTenant('/users/create')->assertForbidden();
        $this->actingAs($manager)->postToTenant('/users', $this->validPayload())->assertForbidden();
    }
}
