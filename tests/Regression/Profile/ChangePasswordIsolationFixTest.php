<?php

namespace Tests\Regression\Profile;

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\ActsAsTenant;
use Tests\TestCase;

class ChangePasswordIsolationFixTest extends TestCase
{
    use ActsAsTenant, RefreshDatabase;

    public function test_changing_password_no_longer_affects_other_users(): void
    {
        $this->setUpTenant();
        $this->seed(PermissionSeeder::class);
        $actor = User::factory()->for($this->tenant)->create(['password' => 'old-pass']);
        $actor->assignRole('Owner');
        $other = User::factory()->for($this->tenant)->create(['password' => 'other-pass']);

        $this->actingAs($actor)->putToTenant('/change-password', [
            'current_password' => 'old-pass',
            'password' => 'new-pass',
            'password_confirmation' => 'new-pass',
        ]);

        $this->assertTrue(Hash::check('other-pass', $other->refresh()->password));
    }
}
