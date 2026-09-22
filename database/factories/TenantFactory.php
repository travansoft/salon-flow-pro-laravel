<?php

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $slug = fake()->unique()->slug(2);

        return [
            'name' => fake()->company(),
            'slug' => $slug,
            'subdomain' => $slug,
            'custom_domain' => null,
            'is_active' => true,
            'default_gst_rate' => 18.00,
        ];
    }
}
