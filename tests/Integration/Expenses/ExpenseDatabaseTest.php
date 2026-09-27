<?php

namespace Tests\Integration\Expenses;

use App\Models\Branch;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Tenant;
use App\Models\User;
use App\Services\BranchContext;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpenseDatabaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_expense_persists_with_category_relationship(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);

        $category = ExpenseCategory::factory()->create(['tenant_id' => $tenant->id]);
        $expense = Expense::factory()->create(['tenant_id' => $tenant->id, 'category_id' => $category->id]);

        $this->assertDatabaseHas('expenses', ['id' => $expense->id, 'category_id' => $category->id]);
        $this->assertTrue($expense->category->is($category));
    }

    public function test_expense_persists_with_created_by_relationship(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);

        $user = User::factory()->for($tenant)->create();
        $expense = Expense::factory()->create(['tenant_id' => $tenant->id, 'created_by' => $user->id]);

        $this->assertTrue($expense->createdBy->is($user));
    }

    public function test_tenant_scope_excludes_expenses_from_other_tenants(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        Expense::factory()->create(['tenant_id' => $tenantA->id]);
        Expense::factory()->create(['tenant_id' => $tenantB->id]);

        app(TenantContext::class)->set($tenantA);
        $branchA = Branch::factory()->create(['tenant_id' => $tenantA->id]);
        app(BranchContext::class)->set($branchA);

        $this->assertSame(1, Expense::count());
    }

    public function test_deleting_an_expense_soft_deletes_it(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);

        $expense = Expense::factory()->create(['tenant_id' => $tenant->id]);
        $expense->delete();

        $this->assertSoftDeleted('expenses', ['id' => $expense->id]);
        $this->assertDatabaseMissing('expenses', ['id' => $expense->id, 'deleted_at' => null]);
    }

    public function test_deleting_a_category_does_not_delete_its_expenses(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);

        $category = ExpenseCategory::factory()->create(['tenant_id' => $tenant->id]);
        $expense = Expense::factory()->create(['tenant_id' => $tenant->id, 'category_id' => $category->id]);

        $category->delete();

        $this->assertDatabaseHas('expenses', ['id' => $expense->id]);
    }
}
