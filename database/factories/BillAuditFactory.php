<?php

namespace Database\Factories;

use App\Models\Bill;
use App\Models\BillAudit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BillAudit>
 */
class BillAuditFactory extends Factory
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
            'branch_id' => $bill->branch_id,
            'bill_id' => $bill->id,
            'action' => BillAudit::ActionEdited,
            'field' => 'notes',
            'old_value' => null,
            'new_value' => 'Updated note',
            'changed_by' => fn (array $attributes) => User::factory()->create(['tenant_id' => $attributes['tenant_id']])->id,
        ];
    }
}
