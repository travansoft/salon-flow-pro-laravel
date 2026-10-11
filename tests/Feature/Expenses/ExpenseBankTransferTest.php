<?php

namespace Tests\Feature\Expenses;

use App\Models\Expense;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsTenant;
use Tests\TestCase;

class ExpenseBankTransferTest extends TestCase
{
    use ActsAsTenant, RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenant();
        $this->seed(PermissionSeeder::class);
        $this->owner = User::factory()->for($this->tenant)->create();
        $this->owner->assignRole('Owner');
    }

    public function test_create_form_offers_bank_transfer(): void
    {
        $response = $this->actingAs($this->owner)->getFromTenant('/expenses/create');

        $response->assertSee('Bank Transfer');
    }

    public function test_owner_can_create_an_expense_paid_by_bank_transfer(): void
    {
        $response = $this->actingAs($this->owner)->postToTenant('/expenses', [
            'description' => 'Supplier payment',
            'amount' => 2500,
            'payment_method' => 'bank_transfer',
            'expense_date' => '2026-08-01',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('expenses', [
            'description' => 'Supplier payment',
            'payment_method' => 'bank_transfer',
        ]);
    }

    public function test_owner_can_change_an_expense_to_bank_transfer(): void
    {
        $expense = Expense::factory()->create([
            'tenant_id' => $this->tenant->id,
            'payment_method' => 'cash',
        ]);

        $response = $this->actingAs($this->owner)->putToTenant("/expenses/{$expense->id}", [
            'description' => $expense->description,
            'amount' => $expense->amount,
            'payment_method' => 'bank_transfer',
            'expense_date' => $expense->expense_date->format('Y-m-d'),
        ]);

        $response->assertRedirect();
        $this->assertSame('bank_transfer', $expense->refresh()->payment_method);
    }

    public function test_unknown_payment_method_is_still_rejected(): void
    {
        $response = $this->actingAs($this->owner)->postToTenant('/expenses', [
            'description' => 'Odd payment',
            'amount' => 100,
            'payment_method' => 'cheque',
            'expense_date' => '2026-08-01',
        ]);

        $response->assertSessionHasErrors('payment_method');
    }

    public function test_expenses_can_be_filtered_by_bank_transfer(): void
    {
        Expense::factory()->create([
            'tenant_id' => $this->tenant->id,
            'description' => 'Paid by bank',
            'payment_method' => 'bank_transfer',
            'expense_date' => now()->format('Y-m-d'),
        ]);
        Expense::factory()->create([
            'tenant_id' => $this->tenant->id,
            'description' => 'Paid by cash',
            'payment_method' => 'cash',
            'expense_date' => now()->format('Y-m-d'),
        ]);

        $response = $this->actingAs($this->owner)->getFromTenant('/expenses?payment_method=bank_transfer');

        $response->assertSee('Paid by bank');
        $response->assertDontSee('Paid by cash');
    }
}
