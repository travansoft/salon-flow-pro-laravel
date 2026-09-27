<?php

namespace Tests\Unit\Staff;

use App\Models\Branch;
use App\Models\StaffProfile;
use App\Models\Tenant;
use App\Services\BranchContext;
use App\Services\StaffService;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;

class StaffLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_disable_login_sets_disabled_at_on_the_linked_user(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);
        $staffProfile = StaffProfile::factory()->create(['tenant_id' => $tenant->id]);

        app(StaffService::class)->disableLogin($staffProfile);

        $this->assertNotNull($staffProfile->user->fresh()->disabled_at);
        $this->assertFalse($staffProfile->user->fresh()->isLoginEnabled());
    }

    public function test_disable_login_deletes_the_users_active_sessions(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);
        $staffProfile = StaffProfile::factory()->create(['tenant_id' => $tenant->id]);
        DB::table('sessions')->insert([
            'id' => 'test-session-id',
            'user_id' => $staffProfile->user_id,
            'payload' => 'x',
            'last_activity' => now()->timestamp,
        ]);

        app(StaffService::class)->disableLogin($staffProfile);

        $this->assertDatabaseMissing('sessions', ['user_id' => $staffProfile->user_id]);
    }

    public function test_disable_login_throws_when_staff_has_no_login(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);
        $staffProfile = StaffProfile::factory()->withoutLogin()->create(['tenant_id' => $tenant->id]);

        $this->expectException(InvalidArgumentException::class);

        app(StaffService::class)->disableLogin($staffProfile);
    }

    public function test_enable_login_clears_disabled_at(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);
        $staffProfile = StaffProfile::factory()->create(['tenant_id' => $tenant->id]);
        app(StaffService::class)->disableLogin($staffProfile);

        app(StaffService::class)->enableLogin($staffProfile);

        $this->assertNull($staffProfile->user->fresh()->disabled_at);
        $this->assertTrue($staffProfile->user->fresh()->isLoginEnabled());
    }

    public function test_enable_login_throws_when_staff_has_no_login(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);
        $staffProfile = StaffProfile::factory()->withoutLogin()->create(['tenant_id' => $tenant->id]);

        $this->expectException(InvalidArgumentException::class);

        app(StaffService::class)->enableLogin($staffProfile);
    }
}
