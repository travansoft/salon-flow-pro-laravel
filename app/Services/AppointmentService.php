<?php

namespace App\Services;

use App\Exceptions\StaffUnavailableException;
use App\Models\Appointment;
use App\Models\AppointmentReminder;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Repositories\Contracts\AppointmentRepositoryInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AppointmentService
{
    public function __construct(
        private AppointmentRepositoryInterface $appointmentRepository,
        private StaffAvailabilityService $availabilityService,
        private TenantContext $tenantContext,
        private BranchContext $branchContext,
    ) {}

    /**
     * @param  array<int, array{service_id: int, staff_profile_id: int}>  $lineItems
     */
    public function book(int $clientId, Carbon $startAt, array $lineItems, ?string $notes = null): Appointment
    {
        $tenant = $this->tenantContext->get();
        $branch = $this->branchContext->get();

        $resolvedLineItems = $this->resolveLineItems($startAt, $lineItems);
        $endAt = collect($resolvedLineItems)->max('end_at');

        foreach ($this->groupByStaff($resolvedLineItems) as $staffProfileId => $staffLineItems) {
            $staffProfile = StaffProfile::findOrFail($staffProfileId);
            $staffStart = collect($staffLineItems)->min('start_at');
            $staffEnd = collect($staffLineItems)->max('end_at');

            if (! $this->availabilityService->isAvailable($staffProfile, $staffStart, $staffEnd)) {
                throw new StaffUnavailableException("{$staffProfile->name} is not available for this time slot.");
            }
        }

        return DB::transaction(function () use ($tenant, $branch, $clientId, $startAt, $endAt, $resolvedLineItems, $notes): Appointment {
            $appointment = $this->appointmentRepository->create([
                'tenant_id' => $tenant->id,
                'branch_id' => $branch->id,
                'client_id' => $clientId,
                'start_at' => $startAt,
                'end_at' => $endAt,
                'status' => Appointment::StatusBooked,
                'notes' => $notes,
            ]);

            foreach ($resolvedLineItems as $item) {
                $appointment->services()->attach($item['service_id'], [
                    'staff_profile_id' => $item['staff_profile_id'],
                    'price_at_booking' => $item['price'],
                    'duration_minutes_at_booking' => $item['duration_minutes'],
                    'start_at' => $item['start_at'],
                    'end_at' => $item['end_at'],
                ]);
            }

            $this->scheduleReminders($appointment);

            return $appointment->load('services');
        });
    }

    public function reschedule(Appointment $appointment, Carbon $newStartAt, ?string $reason, int $changedBy): Appointment
    {
        $deltaMinutes = $appointment->start_at->diffInMinutes($newStartAt, false);
        $durationMinutes = $appointment->start_at->diffInMinutes($appointment->end_at);
        $newEndAt = $newStartAt->copy()->addMinutes($durationMinutes);

        $appointment->loadMissing('services');

        foreach ($appointment->services->groupBy('pivot.staff_profile_id') as $staffProfileId => $servicesForStaff) {
            $staffProfile = StaffProfile::findOrFail($staffProfileId);
            $staffStart = Carbon::parse($servicesForStaff->min('pivot.start_at'))->addMinutes($deltaMinutes);
            $staffEnd = Carbon::parse($servicesForStaff->max('pivot.end_at'))->addMinutes($deltaMinutes);

            if (! $this->availabilityService->isAvailable($staffProfile, $staffStart, $staffEnd, $appointment->id)) {
                throw new StaffUnavailableException("{$staffProfile->name} is not available for this time slot.");
            }
        }

        return DB::transaction(function () use ($appointment, $newEndAt, $newStartAt, $deltaMinutes, $reason, $changedBy): Appointment {
            $this->appointmentRepository->update($appointment, [
                'start_at' => $newStartAt,
                'end_at' => $newEndAt,
            ]);

            foreach ($appointment->services as $service) {
                $appointment->services()->updateExistingPivot($service->id, [
                    'start_at' => Carbon::parse($service->pivot->start_at)->addMinutes($deltaMinutes),
                    'end_at' => Carbon::parse($service->pivot->end_at)->addMinutes($deltaMinutes),
                ]);
            }

            $this->recordStatusChange($appointment, $appointment->status, $appointment->status, $reason, $changedBy);
            $this->scheduleReminders($appointment);

            return $appointment->refresh();
        });
    }

    public function cancel(Appointment $appointment, string $reason, int $changedBy): Appointment
    {
        return DB::transaction(function () use ($appointment, $reason, $changedBy): Appointment {
            $fromStatus = $appointment->status;

            $this->appointmentRepository->update($appointment, [
                'status' => Appointment::StatusCancelled,
                'cancellation_reason' => $reason,
            ]);

            $this->recordStatusChange($appointment, $fromStatus, Appointment::StatusCancelled, $reason, $changedBy);
            $appointment->reminders()->where('status', AppointmentReminder::StatusPending)
                ->update(['status' => AppointmentReminder::StatusCancelled]);

            return $appointment->refresh();
        });
    }

    public function markNoShow(Appointment $appointment, int $changedBy): Appointment
    {
        return DB::transaction(function () use ($appointment, $changedBy): Appointment {
            $fromStatus = $appointment->status;

            $this->appointmentRepository->update($appointment, ['status' => Appointment::StatusNoShow]);
            $this->recordStatusChange($appointment, $fromStatus, Appointment::StatusNoShow, null, $changedBy);

            $this->flagClientIfRepeatedNoShow($appointment);

            return $appointment->refresh();
        });
    }

    /**
     * @param  array<int, array{service_id: int, staff_profile_id: int}>  $lineItems
     * @return array<int, array{service_id: int, staff_profile_id: int, price: float, duration_minutes: int, start_at: Carbon, end_at: Carbon}>
     */
    private function resolveLineItems(Carbon $startAt, array $lineItems): array
    {
        $resolved = [];
        $cursor = $startAt->copy();

        foreach ($lineItems as $item) {
            $service = Service::findOrFail($item['service_id']);
            $lineStart = $cursor->copy();
            $lineEnd = $lineStart->copy()->addMinutes($service->duration_minutes);

            $resolved[] = [
                'service_id' => $service->id,
                'staff_profile_id' => $item['staff_profile_id'],
                'price' => (float) $service->price,
                'duration_minutes' => $service->duration_minutes,
                'start_at' => $lineStart,
                'end_at' => $lineEnd,
            ];

            $cursor = $lineEnd;
        }

        return $resolved;
    }

    /**
     * @param  array<int, array{service_id: int, staff_profile_id: int, price: float, duration_minutes: int, start_at: Carbon, end_at: Carbon}>  $resolvedLineItems
     * @return array<int, array<int, array{service_id: int, staff_profile_id: int, price: float, duration_minutes: int, start_at: Carbon, end_at: Carbon}>>
     */
    private function groupByStaff(array $resolvedLineItems): array
    {
        $grouped = [];

        foreach ($resolvedLineItems as $item) {
            $grouped[$item['staff_profile_id']][] = $item;
        }

        return $grouped;
    }

    private function recordStatusChange(Appointment $appointment, string $fromStatus, string $toStatus, ?string $reason, int $changedBy): void
    {
        $appointment->statusHistories()->create([
            'tenant_id' => $appointment->tenant_id,
            'branch_id' => $appointment->branch_id,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'reason' => $reason,
            'changed_by' => $changedBy,
        ]);
    }

    private function scheduleReminders(Appointment $appointment): void
    {
        $appointment->reminders()->where('status', AppointmentReminder::StatusPending)->delete();

        $appointment->reminders()->create([
            'tenant_id' => $appointment->tenant_id,
            'branch_id' => $appointment->branch_id,
            'type' => AppointmentReminder::TypeConfirmation,
            'channel' => 'whatsapp',
            'scheduled_for' => now(),
            'status' => AppointmentReminder::StatusPending,
        ]);

        $appointment->reminders()->create([
            'tenant_id' => $appointment->tenant_id,
            'branch_id' => $appointment->branch_id,
            'type' => AppointmentReminder::TypeReminder,
            'channel' => 'whatsapp',
            'scheduled_for' => $appointment->start_at->copy()->subHours(2),
            'status' => AppointmentReminder::StatusPending,
        ]);
    }

    private function flagClientIfRepeatedNoShow(Appointment $appointment): void
    {
        $noShowCount = Appointment::where('client_id', $appointment->client_id)
            ->where('status', Appointment::StatusNoShow)
            ->count();

        if ($noShowCount >= 2) {
            $appointment->client->update(['is_frequent_no_show' => true]);
        }
    }
}
