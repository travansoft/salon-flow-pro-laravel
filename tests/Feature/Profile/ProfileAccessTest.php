<?php

namespace Tests\Feature\Profile;

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsTenant;
use Tests\TestCase;

class ProfileAccessTest extends TestCase
{
    use ActsAsTenant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenant();
        $this->seed(PermissionSeeder::class);
    }

    public function test_every_role_can_view_profile_and_change_password_pages(): void
    {
        foreach (['Owner', 'FrontDesk', 'Stylist'] as $role) {
            $user = User::factory()->for($this->tenant)->create();
            $user->assignRole($role);

            $this->actingAs($user)->getFromTenant('/profile')->assertOk();
            $this->actingAs($user)->getFromTenant('/change-password')->assertOk();
        }
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->getFromTenant('/profile')->assertRedirect($this->tenantUrl('/login'));
        $this->getFromTenant('/change-password')->assertRedirect($this->tenantUrl('/login'));
    }

    public function test_profile_shows_name_role_and_username(): void
    {
        $user = User::factory()->for($this->tenant)->create(['name' => 'Asha Nair', 'username' => 'asha.n']);
        $user->assignRole('FrontDesk');

        $response = $this->actingAs($user)->getFromTenant('/profile');

        $response->assertSee('Asha Nair');
        $response->assertSee('FrontDesk');
        $response->assertSee('asha.n');
    }

    public function test_layout_dropdown_contains_profile_password_and_logout(): void
    {
        $user = User::factory()->for($this->tenant)->create();
        $user->assignRole('Owner');

        $response = $this->actingAs($user)->getFromTenant('/profile');

        $response->assertSee('My profile');
        $response->assertSee('Change password');
        $response->assertSee('Logout');
    }
}
