<?php

namespace Database\Factories;

use App\Models\Bill;
use App\Models\BillLineItem;
use App\Models\Branch;
use App\Services\BranchContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BillLineItem>
 */
class BillLineItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $bill = Bill::factory()->create();

        return [
            'tenant_id' => $bill->tenant_id,
            'branch_id' => function (array $attributes) use ($bill) {
                if ($attributes['tenant_id'] === $bill->tenant_id) {
                    return $bill->branch_id;
                }

                $branch = app(BranchContext::class)->get();

                if ($branch && $branch->tenant_id === $attributes['tenant_id']) {
                    return $branch->id;
                }

                return Branch::defaultForTenant($attributes['tenant_id'])->id;
            },
            'bill_id' => $bill->id,
            'description' => fake()->words(2, true),
            'quantity' => 1,
            'unit_price' => 500,
            'tax_rate' => 18.00,
            'line_total' => 500,
        ];
    }
}
