<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\StaffLeaveRequest;
use App\Models\StaffProfile;
use App\Services\BranchContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StaffLeaveRequest>
 */
class StaffLeaveRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $staffProfile = StaffProfile::factory()->create();

        return [
            'tenant_id' => $staffProfile->tenant_id,
            'branch_id' => function (array $attributes) {
                $branch = app(BranchContext::class)->get();

                if ($branch && $branch->tenant_id === $attributes['tenant_id']) {
                    return $branch->id;
                }

                return Branch::defaultForTenant($attributes['tenant_id'])->id;
            },
            'staff_profile_id' => $staffProfile->id,
            'start_date' => now()->addDays(3)->toDateString(),
            'end_date' => now()->addDays(4)->toDateString(),
            'reason' => fake()->sentence(),
            'status' => StaffLeaveRequest::StatusPending,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => StaffLeaveRequest::StatusApproved,
            'decided_at' => now(),
        ]);
    }
}
