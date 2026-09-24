<?php

namespace App\Services;

use App\Models\BridalEngagement;
use App\Repositories\Contracts\BridalEngagementRepositoryInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class BridalEngagementService
{
    public function __construct(
        private BridalEngagementRepositoryInterface $bridalEngagementRepository,
        private AppointmentService $appointmentService,
        private TenantContext $tenantContext,
        private BranchContext $branchContext,
    ) {}

    /**
     * @param  array<int, array{service_id: int}>  $trialLineItems
     * @param  array<int, array{service_id: int}>  $eventLineItems
     * @param  array<int, int>  $travelingStaffProfileIds
     */
    public function createEngagement(
        int $clientId,
        Carbon $eventDate,
        ?string $venue,
        int $trialStaffProfileId,
        Carbon $trialStartAt,
        array $trialLineItems,
        int $eventStaffProfileId,
        Carbon $eventStartAt,
        array $eventLineItems,
        array $travelingStaffProfileIds = [],
        bool $eventIsOnLocation = true,
    ): BridalEngagement {
        $tenant = $this->tenantContext->get();
        $branch = $this->branchContext->get();

        return DB::transaction(function () use (
            $tenant, $branch, $clientId, $eventDate, $venue,
            $trialStaffProfileId, $trialStartAt, $trialLineItems,
            $eventStaffProfileId, $eventStartAt, $eventLineItems,
            $travelingStaffProfileIds, $eventIsOnLocation,
        ): BridalEngagement {
            $engagement = $this->bridalEngagementRepository->create([
                'tenant_id' => $tenant->id,
                'branch_id' => $branch->id,
                'client_id' => $clientId,
                'event_date' => $eventDate->toDateString(),
                'venue' => $venue,
                'status' => BridalEngagement::StatusPlanned,
            ]);

            $trialAppointment = $this->appointmentService->book(
                $clientId,
                $trialStartAt,
                $this->assignStaffToLineItems($trialLineItems, $trialStaffProfileId),
            );
            $trialAppointment->update([
                'bridal_engagement_id' => $engagement->id,
                'engagement_role' => BridalEngagement::RoleTrial,
            ]);

            $eventAppointment = $this->appointmentService->book(
                $clientId,
                $eventStartAt,
                $this->assignStaffToLineItems($eventLineItems, $eventStaffProfileId),
            );
            $eventAppointment->update([
                'bridal_engagement_id' => $engagement->id,
                'engagement_role' => BridalEngagement::RoleEventDay,
                'is_on_location' => $eventIsOnLocation,
                'venue_address' => $eventIsOnLocation ? $venue : null,
            ]);

            if ($travelingStaffProfileIds !== []) {
                $engagement->travelingStaff()->sync($travelingStaffProfileIds);
            }

            return $engagement->refresh()->load(['appointments', 'travelingStaff']);
        });
    }

    public function markTrialCompleted(BridalEngagement $engagement): BridalEngagement
    {
        $engagement->update(['status' => BridalEngagement::StatusTrialCompleted]);

        return $engagement->refresh();
    }

    public function complete(BridalEngagement $engagement): BridalEngagement
    {
        $engagement->update(['status' => BridalEngagement::StatusCompleted]);

        return $engagement->refresh();
    }

    /**
     * @param  array<int, array{service_id: int}>  $lineItems
     * @return array<int, array{service_id: int, staff_profile_id: int}>
     */
    private function assignStaffToLineItems(array $lineItems, int $staffProfileId): array
    {
        return array_map(
            fn (array $item) => ['service_id' => $item['service_id'], 'staff_profile_id' => $staffProfileId],
            $lineItems,
        );
    }
}
