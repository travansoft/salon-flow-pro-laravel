<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Tenant;
use App\Models\WalkIn;
use App\Services\BranchContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WalkIn>
 */
class WalkInFactory extends Factory
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
            'name' => fake()->name(),
            'phone' => fake()->numerify('9#########'),
            'status' => WalkIn::StatusWaiting,
            'joined_at' => now(),
        ];
    }
}
