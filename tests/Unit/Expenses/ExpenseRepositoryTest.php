<?php

namespace Tests\Unit\Expenses;

use App\Models\Branch;
use App\Models\Expense;
use App\Models\Tenant;
use App\Models\User;
use App\Repositories\Eloquent\ExpenseRepository;
use App\Services\BranchContext;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ExpenseRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_find_by_id_returns_the_matching_expense(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);

        $expense = Expense::factory()->create(['tenant_id' => $tenant->id]);

        $repository = new ExpenseRepository(new Expense);

        $this->assertTrue($repository->findById($expense->id)->is($expense));
    }

    public function test_find_by_id_returns_null_when_missing(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);

        $repository = new ExpenseRepository(new Expense);

        $this->assertNull($repository->findById(999999));
    }

    public function test_get_all_returns_expenses_ordered_by_date_descending(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);

        $older = Expense::factory()->create(['tenant_id' => $tenant->id, 'expense_date' => '2026-01-01']);
        $newer = Expense::factory()->create(['tenant_id' => $tenant->id, 'expense_date' => '2026-06-01']);

        $repository = new ExpenseRepository(new Expense);

        $results = $repository->getAll();

        $this->assertTrue($results->first()->is($newer));
        $this->assertTrue($results->last()->is($older));
    }

    public function test_get_between_dates_filters_by_expense_date(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);

        $inRange = Expense::factory()->create(['tenant_id' => $tenant->id, 'expense_date' => '2026-08-10']);
        Expense::factory()->create(['tenant_id' => $tenant->id, 'expense_date' => '2026-09-10']);

        $repository = new ExpenseRepository(new Expense);

        $results = $repository->getBetweenDates(Carbon::parse('2026-08-01'), Carbon::parse('2026-08-31'));

        $this->assertCount(1, $results);
        $this->assertTrue($results->first()->is($inRange));
    }

    public function test_create_persists_a_new_expense(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);

        $repository = new ExpenseRepository(new Expense);

        $created = $repository->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'description' => 'Test expense',
            'amount' => 100,
            'expense_date' => '2026-08-01',
            'created_by' => User::factory()->for($tenant)->create()->id,
        ]);

        $this->assertDatabaseHas('expenses', ['id' => $created->id, 'description' => 'Test expense']);
    }

    public function test_update_persists_changes(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);

        $expense = Expense::factory()->create(['tenant_id' => $tenant->id, 'description' => 'Original']);

        $repository = new ExpenseRepository(new Expense);
        $repository->update($expense, ['description' => 'Updated']);

        $this->assertSame('Updated', $expense->fresh()->description);
    }

    public function test_delete_soft_deletes_the_expense(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);

        $expense = Expense::factory()->create(['tenant_id' => $tenant->id]);

        $repository = new ExpenseRepository(new Expense);

        $this->assertTrue($repository->delete($expense));
        $this->assertSoftDeleted('expenses', ['id' => $expense->id]);
    }
}
