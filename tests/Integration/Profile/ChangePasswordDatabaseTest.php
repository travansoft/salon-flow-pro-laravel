<?php

namespace Tests\Integration\Profile;

use App\Models\User;
use App\Services\ProfileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\ActsAsTenant;
use Tests\TestCase;

class ChangePasswordDatabaseTest extends TestCase
{
    use ActsAsTenant, RefreshDatabase;

    public function test_change_password_stores_a_hash_that_verifies_only_the_new_password(): void
    {
        $this->setUpTenant();
        $user = User::factory()->for($this->tenant)->create(['password' => 'old-pass']);

        app(ProfileService::class)->changePassword($user, 'new-pass');

        $stored = $user->refresh()->password;
        $this->assertNotSame('new-pass', $stored);
        $this->assertTrue(Hash::check('new-pass', $stored));
        $this->assertFalse(Hash::check('old-pass', $stored));
    }
}
