<?php

namespace Tests\Feature\Profile;

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\ActsAsTenant;
use Tests\TestCase;

class ChangePasswordTest extends TestCase
{
    use ActsAsTenant, RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenant();
        $this->seed(PermissionSeeder::class);
        $this->user = User::factory()->for($this->tenant)->create(['password' => 'old-pass']);
        $this->user->assignRole('Stylist');
    }

    public function test_user_can_change_password(): void
    {
        $response = $this->actingAs($this->user)->putToTenant('/change-password', [
            'current_password' => 'old-pass',
            'password' => 'new-pass',
            'password_confirmation' => 'new-pass',
        ]);

        $response->assertRedirect($this->tenantUrl('/profile'));
        $this->assertTrue(Hash::check('new-pass', $this->user->refresh()->password));
    }

    public function test_wrong_current_password_is_rejected(): void
    {
        $response = $this->actingAs($this->user)->putToTenant('/change-password', [
            'current_password' => 'nope',
            'password' => 'new-pass',
            'password_confirmation' => 'new-pass',
        ]);

        $response->assertSessionHasErrors('current_password');
        $this->assertTrue(Hash::check('old-pass', $this->user->refresh()->password));
    }

    public function test_confirmation_mismatch_is_rejected(): void
    {
        $response = $this->actingAs($this->user)->putToTenant('/change-password', [
            'current_password' => 'old-pass',
            'password' => 'new-pass',
            'password_confirmation' => 'different',
        ]);

        $response->assertSessionHasErrors('password');
    }

    public function test_new_password_must_differ_from_current(): void
    {
        $response = $this->actingAs($this->user)->putToTenant('/change-password', [
            'current_password' => 'old-pass',
            'password' => 'old-pass',
            'password_confirmation' => 'old-pass',
        ]);

        $response->assertSessionHasErrors('password');
    }

    public function test_guest_cannot_change_password(): void
    {
        $response = $this->putToTenant('/change-password', ['password' => 'x']);

        $response->assertRedirect($this->tenantUrl('/login'));
    }
}
