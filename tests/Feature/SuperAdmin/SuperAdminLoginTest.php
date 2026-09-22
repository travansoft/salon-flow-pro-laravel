<?php

namespace Tests\Feature\SuperAdmin;

use App\Models\PlatformAdmin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\ActsAsSuperAdmin;
use Tests\TestCase;

class SuperAdminLoginTest extends TestCase
{
    use ActsAsSuperAdmin, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpMainDomain();
    }

    public function test_platform_admin_can_log_in_with_username_and_password(): void
    {
        PlatformAdmin::factory()->create([
            'username' => 'admin',
            'password' => Hash::make('123'),
        ]);

        $response = $this->postToSuperAdmin('/login', [
            'username' => 'admin',
            'password' => '123',
        ]);

        $response->assertRedirect($this->superAdminUrl(''));
        $this->assertAuthenticated('super_admin');
    }

    public function test_login_fails_with_invalid_password(): void
    {
        PlatformAdmin::factory()->create([
            'username' => 'admin',
            'password' => Hash::make('123'),
        ]);

        $response = $this->postToSuperAdmin('/login', [
            'username' => 'admin',
            'password' => 'wrong',
        ]);

        $response->assertSessionHasErrors('username');
        $this->assertGuest('super_admin');
    }

    public function test_platform_admin_can_log_out(): void
    {
        $admin = PlatformAdmin::factory()->create();

        $response = $this->actingAs($admin, 'super_admin')->postToSuperAdmin('/logout');

        $response->assertRedirect($this->superAdminUrl('/login'));
        $this->assertGuest('super_admin');
    }
}
