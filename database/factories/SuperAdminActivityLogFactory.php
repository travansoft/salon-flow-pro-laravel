<?php

namespace Database\Factories;

use App\Models\PlatformAdmin;
use App\Models\SuperAdminActivityLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SuperAdminActivityLog>
 */
class SuperAdminActivityLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'platform_admin_id' => PlatformAdmin::factory(),
            'platform_admin_name' => fake()->name(),
            'action' => 'tenant.created',
            'subject_type' => null,
            'subject_id' => null,
            'description' => fake()->sentence(),
        ];
    }
}
