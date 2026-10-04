<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\BridalEngagement;
use App\Models\Client;
use App\Models\Tenant;
use App\Services\BranchContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BridalEngagement>
 */
class BridalEngagementFactory extends Factory
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
            'client_id' => fn (array $attributes) => Client::factory()->create(['tenant_id' => $attributes['tenant_id']])->id,
            'event_name' => fake()->words(2, true),
            'event_date' => now()->addMonth()->toDateString(),
            'venue_type' => 'studio',
            'has_studio_trial' => false,
            'ready_time' => '07:00',
            'total_amount' => 25000,
            'advance_amount' => 5000,
            'groom_makeup' => false,
            'dress_type' => 'saree',
            'saree_drapist_name' => fake()->name(),
            'status' => BridalEngagement::StatusPlanned,
        ];
    }
}
