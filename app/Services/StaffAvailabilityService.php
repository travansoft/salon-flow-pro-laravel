<?php

namespace App\Services;

use App\Models\StaffProfile;
use App\Repositories\Contracts\AppointmentRepositoryInterface;
use Illuminate\Support\Carbon;

class StaffAvailabilityService
{
    public function __construct(private AppointmentRepositoryInterface $appointmentRepository,
        private TenantContext $tenantContext,
    ) {}

    public function isAvailable(StaffProfile $staffProfile, Carbon $start, Carbon $end, ?int $excludingAppointmentId = null): bool
    {
        if (! $this->tenantContext->get()->check_staff_availability) {
            return true;
        }

        return $this->isWithinWorkingHours($staffProfile, $start, $end)
            && $this->appointmentRepository->getOverlappingForStaff($staffProfile->id, $start, $end, $excludingAppointmentId)->isEmpty();
    }

    private function isWithinWorkingHours(StaffProfile $staffProfile, Carbon $start, Carbon $end): bool
    {
        if (! $start->isSameDay($end)) {
            return false;
        }

        $override = $staffProfile->shifts()->overrides()
            ->whereDate('override_date', $start->toDateString())
            ->first();

        $shift = $override ?? $staffProfile->shifts()->recurring()
            ->where('day_of_week', $start->dayOfWeek)
            ->first();

        if (! $shift || ! $shift->is_working) {
            return false;
        }

        $shiftStart = $start->copy()->setTimeFromTimeString($shift->start_time);
        $shiftEnd = $start->copy()->setTimeFromTimeString($shift->end_time);

        return $start->greaterThanOrEqualTo($shiftStart) && $end->lessThanOrEqualTo($shiftEnd);
    }
}
