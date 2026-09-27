<?php

namespace Tests\Unit\Staff;

use App\Models\Branch;
use App\Models\StaffLeaveRequest;
use App\Models\StaffProfile;
use App\Models\Tenant;
use App\Repositories\Contracts\StaffLeaveRequestRepositoryInterface;
use App\Services\BranchContext;
use App\Services\LeaveRequestService;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class LeaveRequestServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_approve_marks_request_approved_and_blocks_shifts_for_each_day(): void
    {
        $tenant = Tenant::factory()->create();

        $tenantContext = app(TenantContext::class);
        $tenantContext->set($tenant);

        $branchContext = app(BranchContext::class);
        $branchContext->set(Branch::factory()->create(['tenant_id' => $tenant->id]));

        $staffProfile = StaffProfile::factory()->create(['tenant_id' => $tenant->id]);
        $decidedBy = $staffProfile->user_id;

        $leaveRequest = StaffLeaveRequest::factory()->create([
            'tenant_id' => $tenant->id,
            'staff_profile_id' => $staffProfile->id,
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-02',
        ]);

        $repository = Mockery::mock(StaffLeaveRequestRepositoryInterface::class);
        $repository->shouldReceive('update')
            ->once()
            ->andReturnUsing(function ($request, array $data) {
                $request->update($data);

                return $request;
            });

        $service = new LeaveRequestService($repository, $tenantContext, $branchContext);

        $service->approve($leaveRequest, $decidedBy, 'Approved for vacation');

        $this->assertSame('approved', $leaveRequest->fresh()->status);
        $this->assertDatabaseHas('staff_shifts', [
            'staff_profile_id' => $staffProfile->id,
            'override_date' => '2026-09-01 00:00:00',
            'is_working' => false,
        ]);
        $this->assertDatabaseHas('staff_shifts', [
            'staff_profile_id' => $staffProfile->id,
            'override_date' => '2026-09-02 00:00:00',
            'is_working' => false,
        ]);
    }

    public function test_reject_marks_request_rejected_without_blocking_shifts(): void
    {
        $tenant = Tenant::factory()->create();

        $tenantContext = app(TenantContext::class);
        $tenantContext->set($tenant);

        $branchContext = app(BranchContext::class);
        $branchContext->set(Branch::factory()->create(['tenant_id' => $tenant->id]));

        $staffProfile = StaffProfile::factory()->create(['tenant_id' => $tenant->id]);
        $decidedBy = $staffProfile->user_id;

        $leaveRequest = StaffLeaveRequest::factory()->create([
            'tenant_id' => $tenant->id,
            'staff_profile_id' => $staffProfile->id,
        ]);

        $repository = Mockery::mock(StaffLeaveRequestRepositoryInterface::class);
        $repository->shouldReceive('update')
            ->once()
            ->andReturnUsing(function ($request, array $data) {
                $request->update($data);

                return $request;
            });

        $service = new LeaveRequestService($repository, $tenantContext, $branchContext);

        $service->reject($leaveRequest, $decidedBy);

        $this->assertSame('rejected', $leaveRequest->fresh()->status);
        $this->assertDatabaseCount('staff_shifts', 0);
    }
}
