<?php

namespace Tests\Integration\Expenses;

use App\Models\Expense;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsTenant;
use Tests\TestCase;

class ExpensePartyGstDatabaseTest extends TestCase
{
    use ActsAsTenant, RefreshDatabase;

    public function test_party_gst_and_split_amounts_persist_and_cast_to_decimals(): void
    {
        $this->setUpTenant();

        $expense = Expense::factory()->create([
            'tenant_id' => $this->tenant->id,
            'party_name' => 'Glam Supplies',
            'gst_number' => '29ABCDE1234F1Z5',
            'base_amount' => 1000,
            'gst_amount' => 180,
            'amount' => 1180,
        ]);

        $this->assertDatabaseHas('expenses', ['id' => $expense->id, 'party_name' => 'Glam Supplies', 'gst_number' => '29ABCDE1234F1Z5']);
        $this->assertSame('180.00', $expense->fresh()->gst_amount);
    }
}
