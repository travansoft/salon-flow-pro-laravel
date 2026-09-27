<?php

namespace Tests\Regression\Expenses;

use App\Models\Branch;
use App\Models\Expense;
use App\Models\Tenant;
use App\Repositories\Eloquent\ExpenseCategoryRepository;
use App\Repositories\Eloquent\ExpenseRepository;
use App\Services\BranchContext;
use App\Services\ExpenseService;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TotalForMonthIncludesLastDayOfMonthFixTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Bug risk: a naive "between month start and month end" range built from
     * Carbon::endOfMonth() without normalising to end-of-day can silently
     * exclude an expense dated on the last calendar day of the month,
     * because the date column has no time component to compare against a
     * timestamp boundary. This proves the last day is still included.
     */
    public function test_expense_on_the_last_day_of_the_month_is_included_in_the_total(): void
    {
        $tenant = Tenant::factory()->create();
        $tenantContext = app(TenantContext::class);
        $tenantContext->set($tenant);
        $branchContext = app(BranchContext::class);
        $branchContext->set(Branch::factory()->create(['tenant_id' => $tenant->id]));

        Expense::factory()->create(['tenant_id' => $tenant->id, 'amount' => 100, 'expense_date' => '2026-02-01']);
        Expense::factory()->create(['tenant_id' => $tenant->id, 'amount' => 50, 'expense_date' => '2026-02-28']);

        $service = new ExpenseService(
            app(ExpenseRepository::class),
            app(ExpenseCategoryRepository::class),
            $tenantContext,
            $branchContext,
        );

        $total = $service->totalForMonth(Carbon::parse('2026-02-15'));

        $this->assertSame('150.00', $total);
    }

    /**
     * Bug risk: passing the 1st of the month without normalising to
     * start-of-day could exclude an expense also dated on the 1st if the
     * boundary comparison were built incorrectly.
     */
    public function test_expense_on_the_first_day_of_the_month_is_included_in_the_total(): void
    {
        $tenant = Tenant::factory()->create();
        $tenantContext = app(TenantContext::class);
        $tenantContext->set($tenant);
        $branchContext = app(BranchContext::class);
        $branchContext->set(Branch::factory()->create(['tenant_id' => $tenant->id]));

        Expense::factory()->create(['tenant_id' => $tenant->id, 'amount' => 75, 'expense_date' => '2026-03-01']);

        $service = new ExpenseService(
            app(ExpenseRepository::class),
            app(ExpenseCategoryRepository::class),
            $tenantContext,
            $branchContext,
        );

        $total = $service->totalForMonth(Carbon::parse('2026-03-01'));

        $this->assertSame('75.00', $total);
    }
}
