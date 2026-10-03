<?php

namespace Database\Factories;

use App\Models\Service;
use App\Models\ServiceComboItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceComboItem>
 */
class ServiceComboItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => fn () => Service::factory()->create()->tenant_id,
            'branch_id' => fn (array $attributes) => Service::factory()->create(['tenant_id' => $attributes['tenant_id']])->branch_id,
            'combo_service_id' => fn (array $attributes) => Service::factory()->create([
                'tenant_id' => $attributes['tenant_id'],
                'branch_id' => $attributes['branch_id'],
                'is_combo' => true,
            ])->id,
            'component_service_id' => fn (array $attributes) => Service::factory()->create([
                'tenant_id' => $attributes['tenant_id'],
                'branch_id' => $attributes['branch_id'],
            ])->id,
            'price' => fake()->randomElement([199, 299, 499, 799]),
            'sort_order' => 0,
        ];
    }
}
