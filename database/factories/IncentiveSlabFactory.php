<?php

namespace Database\Factories;

use App\Models\IncentiveSlab;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IncentiveSlab>
 */
class IncentiveSlabFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'tenant_id' => fn () => Tenant::factory()->create()->id,
            'min_achievement_percent' => fake()->unique()->numberBetween(1, 200),
            'incentive_percent' => fake()->randomElement([3, 4, 5]),
        ];
    }
}
