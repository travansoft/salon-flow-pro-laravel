<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\AppointmentStatusHistory;
use App\Models\Branch;
use App\Services\BranchContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AppointmentStatusHistory>
 */
class AppointmentStatusHistoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $appointment = Appointment::factory()->create();

        return [
            'tenant_id' => $appointment->tenant_id,
            'branch_id' => function (array $attributes) use ($appointment) {
                if ($attributes['tenant_id'] === $appointment->tenant_id) {
                    return $appointment->branch_id;
                }

                $branch = app(BranchContext::class)->get();

                if ($branch && $branch->tenant_id === $attributes['tenant_id']) {
                    return $branch->id;
                }

                return Branch::defaultForTenant($attributes['tenant_id'])->id;
            },
            'appointment_id' => $appointment->id,
            'from_status' => 'booked',
            'to_status' => 'cancelled',
            'reason' => 'client_requested',
        ];
    }
}
