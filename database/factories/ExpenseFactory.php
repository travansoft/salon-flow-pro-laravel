<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Expense;
use App\Models\Tenant;
use App\Models\User;
use App\Services\BranchContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Expense>
 */
class ExpenseFactory extends Factory
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
            'category_id' => null,
            'description' => fake()->randomElement(['Monthly rent', 'Electricity bill', 'Hair product restock', 'Salary payout', 'Social media ads']),
            'amount' => fake()->randomElement([499, 999, 1999, 4999, 9999]),
            'is_recurring' => false,
            'recurrence_interval' => null,
            'expense_date' => fake()->dateTimeBetween('-1 month', 'now')->format('Y-m-d'),
            'receipt_path' => null,
            'created_by' => User::factory(),
        ];
    }

    public function recurring(string $interval = 'monthly'): static
    {
        return $this->state(fn (array $attributes) => [
            'is_recurring' => true,
            'recurrence_interval' => $interval,
        ]);
    }
}
