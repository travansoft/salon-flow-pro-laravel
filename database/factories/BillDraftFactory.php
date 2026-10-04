<?php

namespace Database\Factories;

use App\Models\BillDraft;
use App\Models\Branch;
use App\Models\Tenant;
use App\Models\User;
use App\Services\BranchContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BillDraft>
 */
class BillDraftFactory extends Factory
{
    /** @return array<string, mixed> */
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
            'user_id' => fn (array $attributes) => User::factory()->create(['tenant_id' => $attributes['tenant_id']])->id,
            'client_name' => fake()->name(),
            'client_phone' => fake()->numerify('##########'),
            'item_count' => 1,
            'total' => 590,
            'payload' => [
                'client_name' => 'Draft client',
                'lines' => [],
                'discount_mode' => 'percent',
                'discount_value' => '',
                'payment_method' => 'upi',
                'notes' => '',
            ],
        ];
    }
}
