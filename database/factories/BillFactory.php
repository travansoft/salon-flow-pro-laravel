<?php

namespace Database\Factories;

use App\Models\Bill;
use App\Models\Client;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Bill>
 */
class BillFactory extends Factory
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
            'client_id' => fn (array $attributes) => Client::factory()->create(['tenant_id' => $attributes['tenant_id']])->id,
            'bill_number' => fake()->unique()->numberBetween(1, 100000),
            'subtotal' => 500,
            'tax_amount' => 90,
            'cgst_amount' => 45,
            'sgst_amount' => 45,
            'igst_amount' => 0,
            'total' => 590,
            'status' => Bill::StatusUnpaid,
            'created_by' => fn (array $attributes) => User::factory()->create(['tenant_id' => $attributes['tenant_id']])->id,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Bill::StatusPaid,
            'amount_paid' => $attributes['total'] ?? 590,
        ]);
    }
}
