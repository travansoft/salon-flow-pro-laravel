<?php

namespace Tests\Integration\SuperAdmin;

use App\Models\PlatformAdmin;
use Database\Seeders\PlatformAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PlatformAdminSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeding_creates_the_default_admin_account_with_password_123(): void
    {
        $this->seed(PlatformAdminSeeder::class);

        $admin = PlatformAdmin::where('username', 'admin')->first();

        $this->assertNotNull($admin);
        $this->assertTrue(Hash::check('123', $admin->password));
    }

    public function test_seeding_twice_does_not_duplicate_the_default_admin(): void
    {
        $this->seed(PlatformAdminSeeder::class);
        $this->seed(PlatformAdminSeeder::class);

        $this->assertSame(1, PlatformAdmin::where('username', 'admin')->count());
    }
}
