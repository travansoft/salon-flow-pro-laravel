<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\StaffIncentive;
use App\Models\StaffProfile;
use App\Models\Tenant;
use App\Models\User;
use App\Services\BranchContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StaffIncentive>
 */
class StaffIncentiveFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => fn () => Tenant::factory()->create()->id,
            'branch_id' => function (array $attributes) {
                $branch = app(BranchContext::class)->get();

                if ($branch && $branch->tenant_id === $attributes['tenant_id']) {
                    return $branch->id;
                }

                return Branch::defaultForTenant($attributes['tenant_id'])->id;
            },
            'staff_profile_id' => StaffProfile::factory(),
            'amount' => fake()->randomElement([250, 500, 1000, 1500]),
            'reason' => fake()->randomElement(['Client praise', 'Top performer of the month', 'Referral bonus']),
            'awarded_date' => fake()->dateTimeBetween('-3 months', 'now')->format('Y-m-d'),
            'awarded_by' => User::factory(),
        ];
    }
}
