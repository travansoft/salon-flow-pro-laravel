<?php

namespace Tests\Regression\Staff;

use App\Models\Branch;
use App\Models\StaffProfile;
use App\Services\BranchContext;
use App\Services\StaffService;
use App\Services\TenantContext;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\ActsAsTenant;
use Tests\TestCase;

class DisablingLoginKillsActiveSessionFixTest extends TestCase
{
    use ActsAsTenant, RefreshDatabase;

    /**
     * Bug scenario this guards against: disabling a staff member's login only
     * set a flag on the User row, but their already-authenticated browser
     * session kept working until it expired naturally — an admin revoking
     * access had no way to force an immediate logout. StaffService::disableLogin()
     * now deletes that user's rows from the sessions table as part of the same
     * transaction, so a subsequent request from that session is unauthenticated.
     */
    public function test_disabling_login_removes_the_staff_members_existing_session_row(): void
    {
        $this->setUpTenant();
        $this->seed(PermissionSeeder::class);

        app(TenantContext::class)->set($this->tenant);
        $branch = Branch::factory()->create(['tenant_id' => $this->tenant->id]);
        app(BranchContext::class)->set($branch);
        $staffProfile = StaffProfile::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAs($staffProfile->user)->getFromTenant('/dashboard')->assertOk();

        $sessionId = 'regression-session-'.$staffProfile->user_id;
        DB::table('sessions')->insert([
            'id' => $sessionId,
            'user_id' => $staffProfile->user_id,
            'payload' => 'x',
            'last_activity' => now()->timestamp,
        ]);

        app(StaffService::class)->disableLogin($staffProfile);

        $this->assertDatabaseMissing('sessions', ['user_id' => $staffProfile->user_id]);
    }
}
