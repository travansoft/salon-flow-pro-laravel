<?php

namespace Tests\Unit\Expenses;

use App\Models\Branch;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Tenant;
use App\Models\User;
use App\Repositories\Eloquent\ExpenseCategoryRepository;
use App\Repositories\Eloquent\ExpenseRepository;
use App\Services\BranchContext;
use App\Services\ExpenseService;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpenseGstSplitServiceTest extends TestCase
{
    use RefreshDatabase;

    private ExpenseService $service;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $tenant = Tenant::factory()->create();
        $tenantContext = app(TenantContext::class);
        $tenantContext->set($tenant);
        $branchContext = app(BranchContext::class);
        $branchContext->set(Branch::factory()->create(['tenant_id' => $tenant->id]));
        $this->owner = User::factory()->for($tenant)->create();

        $this->service = new ExpenseService(
            new ExpenseRepository(new Expense),
            new ExpenseCategoryRepository(new ExpenseCategory),
            $tenantContext,
            $branchContext,
        );
    }

    public function test_create_stores_party_gst_number_and_calculated_total(): void
    {
        $expense = $this->service->create([
            'description' => 'Hair products',
            'party_name' => 'Glam Supplies',
            'gst_number' => '29ABCDE1234F1Z5',
            'amount' => 1000,
            'gst_amount' => 180,
            'expense_date' => '2026-10-01',
            'created_by' => $this->owner->id,
        ]);

        $this->assertSame('Glam Supplies', $expense->party_name);
        $this->assertSame('29ABCDE1234F1Z5', $expense->gst_number);
        $this->assertSame('1000.00', $expense->base_amount);
        $this->assertSame('180.00', $expense->gst_amount);
        $this->assertSame('1180.00', $expense->amount);
    }

    public function test_create_without_gst_uses_base_amount_as_total(): void
    {
        $expense = $this->service->create([
            'description' => 'Tea',
            'amount' => 250.50,
            'expense_date' => '2026-10-01',
            'created_by' => $this->owner->id,
        ]);

        $this->assertSame('0.00', $expense->gst_amount);
        $this->assertSame('250.50', $expense->amount);
    }

    public function test_update_recalculates_total_when_gst_changes(): void
    {
        $expense = $this->service->create([
            'description' => 'Towels',
            'amount' => 1000,
            'gst_amount' => 50,
            'expense_date' => '2026-10-01',
            'created_by' => $this->owner->id,
        ]);

        $updated = $this->service->update($expense, ['gst_amount' => 120]);

        $this->assertSame('1000.00', $updated->base_amount);
        $this->assertSame('1120.00', $updated->amount);
    }
}
