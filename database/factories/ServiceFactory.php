<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Service;
use App\Models\Tenant;
use App\Services\BranchContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
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
            'name' => fake()->randomElement(['Haircut', 'Hair Spa', 'Manicure', 'Pedicure', 'Facial', 'Hair Color']),
            'code' => fake()->unique()->numerify('###'),
            'category_id' => null,
            'price' => fake()->randomElement([299, 499, 799, 1299, 1999]),
            'duration_minutes' => fake()->randomElement([30, 45, 60, 90]),
            'is_active' => true,
            'requires_rate_confirmation' => false,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function requiresRateConfirmation(): static
    {
        return $this->state(fn (array $attributes) => [
            'requires_rate_confirmation' => true,
        ]);
    }
}
