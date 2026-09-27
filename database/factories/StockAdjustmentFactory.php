<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Product;
use App\Models\StockAdjustment;
use App\Models\Tenant;
use App\Models\User;
use App\Services\BranchContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockAdjustment>
 */
class StockAdjustmentFactory extends Factory
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
            'product_id' => Product::factory(),
            'adjusted_by' => User::factory(),
            'quantity_delta' => fake()->randomElement([5, -5, 10, -10]),
            'reason' => fake()->randomElement(['Stock count', 'Damaged', 'Restock']),
        ];
    }
}
