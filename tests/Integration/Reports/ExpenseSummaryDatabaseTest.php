<?php

namespace Tests\Integration\Reports;

use App\Models\Branch;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Tenant;
use App\Repositories\Contracts\ExpenseRepositoryInterface;
use App\Services\BranchContext;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ExpenseSummaryDatabaseTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($this->tenant);
        app(BranchContext::class)->set(Branch::factory()->create(['tenant_id' => $this->tenant->id]));
    }

    private function expense(array $attributes): Expense
    {
        return Expense::factory()->create(['tenant_id' => $this->tenant->id, ...$attributes]);
    }

    public function test_totals_by_category_groups_sums_and_includes_uncategorised(): void
    {
        $rent = ExpenseCategory::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Rent']);
        $this->expense(['category_id' => $rent->id, 'amount' => 1000, 'expense_date' => '2026-10-02']);
        $this->expense(['category_id' => $rent->id, 'amount' => 500, 'expense_date' => '2026-10-10']);
        $this->expense(['category_id' => null, 'amount' => 200, 'expense_date' => '2026-10-05']);
        $this->expense(['category_id' => $rent->id, 'amount' => 9999, 'expense_date' => '2026-09-30']);

        $rows = app(ExpenseRepositoryInterface::class)
            ->totalsByCategoryBetween(Carbon::parse('2026-10-01'), Carbon::parse('2026-10-31'));

        $this->assertCount(2, $rows);
        $this->assertSame('Rent', $rows[0]->name);
        $this->assertEquals(1500, $rows[0]->total);
        $this->assertSame(2, (int) $rows[0]->expense_count);
        $this->assertNull($rows[1]->category_id);
        $this->assertEquals(200, $rows[1]->total);
    }

    public function test_get_filtered_applies_category_payment_method_and_search(): void
    {
        $rent = ExpenseCategory::factory()->create(['tenant_id' => $this->tenant->id]);
        $match = $this->expense(['category_id' => $rent->id, 'payment_method' => 'upi', 'description' => 'Shop rent', 'expense_date' => '2026-10-02']);
        $this->expense(['category_id' => $rent->id, 'payment_method' => 'cash', 'description' => 'Shop rent', 'expense_date' => '2026-10-03']);
        $this->expense(['category_id' => null, 'payment_method' => 'upi', 'description' => 'Shop rent', 'expense_date' => '2026-10-04']);

        $results = app(ExpenseRepositoryInterface::class)->getFiltered(
            Carbon::parse('2026-10-01'),
            Carbon::parse('2026-10-31'),
            ['category_id' => (string) $rent->id, 'payment_method' => 'upi', 'search' => 'RENT'],
        );

        $this->assertCount(1, $results);
        $this->assertTrue($results->first()->is($match));
    }

    public function test_get_filtered_uncategorised_returns_only_expenses_without_category(): void
    {
        $category = ExpenseCategory::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->expense(['category_id' => $category->id, 'expense_date' => '2026-10-02']);
        $loose = $this->expense(['category_id' => null, 'expense_date' => '2026-10-03']);

        $results = app(ExpenseRepositoryInterface::class)->getFiltered(
            Carbon::parse('2026-10-01'),
            Carbon::parse('2026-10-31'),
            ['category_id' => 'none'],
        );

        $this->assertCount(1, $results);
        $this->assertTrue($results->first()->is($loose));
    }

    public function test_totals_by_category_never_include_other_tenants(): void
    {
        $this->expense(['amount' => 100, 'expense_date' => '2026-10-02']);
        Expense::factory()->create(['amount' => 5000, 'expense_date' => '2026-10-02']);

        $rows = app(ExpenseRepositoryInterface::class)
            ->totalsByCategoryBetween(Carbon::parse('2026-10-01'), Carbon::parse('2026-10-31'));

        $this->assertCount(1, $rows);
        $this->assertEquals(100, $rows[0]->total);
    }
}
