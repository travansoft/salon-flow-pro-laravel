<?php

namespace Tests\Feature\SuperAdmin;

use App\Models\PlatformAdmin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\ActsAsSuperAdmin;
use Tests\TestCase;

class SuperAdminProfileTest extends TestCase
{
    use ActsAsSuperAdmin, RefreshDatabase;

    private PlatformAdmin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpMainDomain();
        $this->admin = PlatformAdmin::factory()->create(['password' => Hash::make('oldpass')]);
    }

    public function test_super_admin_can_view_their_own_profile(): void
    {
        $response = $this->actingAs($this->admin, 'super_admin')->getFromSuperAdmin('/profile');

        $response->assertOk();
    }

    public function test_super_admin_can_update_name_without_touching_password(): void
    {
        $originalPassword = $this->admin->password;

        $response = $this->actingAs($this->admin, 'super_admin')->putToSuperAdmin('/profile', [
            'name' => 'New Name',
            'username' => $this->admin->username,
        ]);

        $response->assertRedirect();
        $this->admin->refresh();
        $this->assertSame('New Name', $this->admin->name);
        $this->assertSame($originalPassword, $this->admin->password);
    }

    public function test_super_admin_can_change_their_password_with_correct_current_password(): void
    {
        $response = $this->actingAs($this->admin, 'super_admin')->putToSuperAdmin('/profile', [
            'name' => $this->admin->name,
            'username' => $this->admin->username,
            'current_password' => 'oldpass',
            'password' => 'newpass123',
            'password_confirmation' => 'newpass123',
        ]);

        $response->assertRedirect();
        $this->assertTrue(Hash::check('newpass123', $this->admin->refresh()->password));
    }

    public function test_password_change_fails_with_incorrect_current_password(): void
    {
        $response = $this->actingAs($this->admin, 'super_admin')->putToSuperAdmin('/profile', [
            'name' => $this->admin->name,
            'username' => $this->admin->username,
            'current_password' => 'wrongpass',
            'password' => 'newpass123',
            'password_confirmation' => 'newpass123',
        ]);

        $response->assertSessionHasErrors('current_password');
        $this->assertTrue(Hash::check('oldpass', $this->admin->refresh()->password));
    }

    public function test_password_confirmation_must_match(): void
    {
        $response = $this->actingAs($this->admin, 'super_admin')->putToSuperAdmin('/profile', [
            'name' => $this->admin->name,
            'username' => $this->admin->username,
            'current_password' => 'oldpass',
            'password' => 'newpass123',
            'password_confirmation' => 'different',
        ]);

        $response->assertSessionHasErrors('password');
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->getFromSuperAdmin('/profile');

        $response->assertRedirect($this->superAdminUrl('/login'));
    }
}
