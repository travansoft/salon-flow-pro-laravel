<?php

namespace Tests\Regression\Expenses;

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsTenant;
use Tests\TestCase;

class ExpenseTotalIncludesGstFixTest extends TestCase
{
    use ActsAsTenant, RefreshDatabase;

    public function test_expense_total_no_longer_ignores_gst_amount(): void
    {
        $this->setUpTenant();
        $this->seed(PermissionSeeder::class);
        $owner = User::factory()->for($this->tenant)->create();
        $owner->assignRole('Owner');

        $this->actingAs($owner)->postToTenant('/expenses', [
            'description' => 'Equipment',
            'amount' => 500,
            'gst_amount' => 90,
            'expense_date' => now()->toDateString(),
        ]);

        $this->assertDatabaseHas('expenses', ['description' => 'Equipment', 'amount' => 590]);
    }
}
