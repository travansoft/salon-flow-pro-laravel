<?php

namespace Tests\Regression\Expenses;

use App\Models\Branch;
use App\Models\Expense;
use App\Models\Tenant;
use App\Models\User;
use App\Repositories\Eloquent\ExpenseCategoryRepository;
use App\Repositories\Eloquent\ExpenseRepository;
use App\Services\BranchContext;
use App\Services\ExpenseService;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecurrenceIntervalClearedOnUpdateFixTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Bug risk: if a caller sends is_recurring=false alongside a stale
     * recurrence_interval value (e.g. an unmodified hidden form field), a
     * naive update that copies validated data straight into the repository
     * would leave a dangling interval on an expense that is no longer
     * recurring. This proves the service always clears it server-side.
     */
    public function test_stale_recurrence_interval_is_discarded_when_is_recurring_is_set_to_false(): void
    {
        $tenant = Tenant::factory()->create();
        $tenantContext = app(TenantContext::class);
        $tenantContext->set($tenant);
        $branchContext = app(BranchContext::class);
        $branchContext->set(Branch::factory()->create(['tenant_id' => $tenant->id]));
        $owner = User::factory()->for($tenant)->create();

        $expense = Expense::factory()->recurring('yearly')->create([
            'tenant_id' => $tenant->id,
            'created_by' => $owner->id,
        ]);

        $service = new ExpenseService(
            app(ExpenseRepository::class),
            app(ExpenseCategoryRepository::class),
            $tenantContext,
            $branchContext,
        );

        $updated = $service->update($expense, [
            'is_recurring' => false,
            'recurrence_interval' => 'yearly',
        ]);

        $this->assertFalse($updated->is_recurring);
        $this->assertNull($updated->recurrence_interval);
    }

    public function test_create_with_recurring_false_and_a_stray_interval_still_saves_null(): void
    {
        $tenant = Tenant::factory()->create();
        $tenantContext = app(TenantContext::class);
        $tenantContext->set($tenant);
        $branchContext = app(BranchContext::class);
        $branchContext->set(Branch::factory()->create(['tenant_id' => $tenant->id]));
        $owner = User::factory()->for($tenant)->create();

        $service = new ExpenseService(
            app(ExpenseRepository::class),
            app(ExpenseCategoryRepository::class),
            $tenantContext,
            $branchContext,
        );

        $created = $service->create([
            'description' => 'Stray interval on create',
            'amount' => 42,
            'is_recurring' => false,
            'recurrence_interval' => 'weekly',
            'expense_date' => '2026-08-01',
            'created_by' => $owner->id,
        ]);

        $this->assertNull($created->recurrence_interval);
    }
}
