<?php

namespace Tests\Integration\Staff;

use App\Models\Branch;
use App\Models\StaffLeaveRequest;
use App\Models\StaffProfile;
use App\Models\Tenant;
use App\Services\BranchContext;
use App\Services\LeaveRequestService;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeaveRequestShiftBlockingTest extends TestCase
{
    use RefreshDatabase;

    public function test_approving_a_leave_request_creates_blocked_shift_overrides_in_database(): void
    {
        $tenant = Tenant::factory()->create();
        $staffProfile = StaffProfile::factory()->create(['tenant_id' => $tenant->id]);

        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);

        $leaveRequest = StaffLeaveRequest::factory()->create([
            'tenant_id' => $tenant->id,
            'staff_profile_id' => $staffProfile->id,
            'start_date' => '2026-10-10',
            'end_date' => '2026-10-11',
        ]);

        app(LeaveRequestService::class)->approve($leaveRequest, $staffProfile->user_id);

        $this->assertDatabaseHas('staff_shifts', [
            'staff_profile_id' => $staffProfile->id,
            'override_date' => '2026-10-10 00:00:00',
            'is_working' => false,
        ]);
        $this->assertDatabaseHas('staff_shifts', [
            'staff_profile_id' => $staffProfile->id,
            'override_date' => '2026-10-11 00:00:00',
            'is_working' => false,
        ]);
    }
}
