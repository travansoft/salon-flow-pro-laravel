<?php

namespace Tests\Regression\Expenses;

use App\Models\Expense;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsTenant;
use Tests\TestCase;

class DayBookBankTransferExpenseFixTest extends TestCase
{
    use ActsAsTenant, RefreshDatabase;

    public function test_day_book_no_longer_errors_on_a_bank_transfer_expense(): void
    {
        $this->setUpTenant();
        $this->seed(PermissionSeeder::class);
        $owner = User::factory()->for($this->tenant)->create();
        $owner->assignRole('Owner');
        Expense::factory()->create([
            'tenant_id' => $this->tenant->id,
            'description' => 'Landlord transfer',
            'payment_method' => 'bank_transfer',
            'amount' => 900,
            'expense_date' => now()->format('Y-m-d'),
        ]);

        $response = $this->actingAs($owner)->getFromTenant('/reports/day-book');

        $response->assertOk();
        $response->assertSee('Landlord transfer');
    }
}
