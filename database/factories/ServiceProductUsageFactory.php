<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Product;
use App\Models\Service;
use App\Models\ServiceProductUsage;
use App\Models\Tenant;
use App\Services\BranchContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceProductUsage>
 */
class ServiceProductUsageFactory extends Factory
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
            'service_id' => Service::factory(),
            'product_id' => Product::factory(),
            'quantity_used' => fake()->randomElement([5, 10, 15, 20]),
        ];
    }
}
