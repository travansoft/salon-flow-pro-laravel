<?php

namespace App\Services;

use App\Models\StaffLeaveRequest;
use App\Models\StaffShift;
use App\Repositories\Contracts\StaffLeaveRequestRepositoryInterface;
use Illuminate\Support\Facades\DB;

class LeaveRequestService
{
    public function __construct(
        private StaffLeaveRequestRepositoryInterface $leaveRequestRepository,
        private TenantContext $tenantContext,
        private BranchContext $branchContext,
    ) {}

    /** @param array<string, mixed> $data */
    public function request(array $data): StaffLeaveRequest
    {
        $tenant = $this->tenantContext->get();
        $branch = $this->branchContext->get();

        return $this->leaveRequestRepository->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'staff_profile_id' => $data['staff_profile_id'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'reason' => $data['reason'] ?? null,
            'status' => StaffLeaveRequest::StatusPending,
        ]);
    }

    public function approve(StaffLeaveRequest $leaveRequest, int $decidedBy, ?string $note = null): StaffLeaveRequest
    {
        return DB::transaction(function () use ($leaveRequest, $decidedBy, $note): StaffLeaveRequest {
            $this->leaveRequestRepository->update($leaveRequest, [
                'status' => StaffLeaveRequest::StatusApproved,
                'decided_by' => $decidedBy,
                'decided_at' => now(),
                'decision_note' => $note,
            ]);

            $this->blockShiftsForLeave($leaveRequest);

            return $leaveRequest->refresh();
        });
    }

    public function reject(StaffLeaveRequest $leaveRequest, int $decidedBy, ?string $note = null): StaffLeaveRequest
    {
        return $this->leaveRequestRepository->update($leaveRequest, [
            'status' => StaffLeaveRequest::StatusRejected,
            'decided_by' => $decidedBy,
            'decided_at' => now(),
            'decision_note' => $note,
        ]);
    }

    private function blockShiftsForLeave(StaffLeaveRequest $leaveRequest): void
    {
        $tenant = $this->tenantContext->get();

        $period = $leaveRequest->start_date->toPeriod($leaveRequest->end_date);

        foreach ($period as $date) {
            StaffShift::query()->updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'branch_id' => $leaveRequest->branch_id,
                    'staff_profile_id' => $leaveRequest->staff_profile_id,
                    'override_date' => $date->toDateString(),
                ],
                [
                    'day_of_week' => null,
                    'start_time' => '00:00',
                    'end_time' => '00:00',
                    'is_working' => false,
                ]
            );
        }
    }
}
