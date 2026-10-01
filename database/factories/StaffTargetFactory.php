<?php

namespace Database\Factories;

use App\Models\StaffProfile;
use App\Models\StaffTarget;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StaffTarget>
 */
class StaffTargetFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'tenant_id' => fn () => Tenant::factory()->create()->id,
            'staff_profile_id' => fn (array $attributes) => StaffProfile::factory()->create(['tenant_id' => $attributes['tenant_id']])->id,
            'month' => now()->startOfMonth()->toDateString(),
            'target_amount' => 150000,
        ];
    }
}
