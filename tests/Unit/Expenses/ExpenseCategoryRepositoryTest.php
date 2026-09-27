<?php

namespace Tests\Unit\Expenses;

use App\Models\Branch;
use App\Models\ExpenseCategory;
use App\Models\Tenant;
use App\Repositories\Eloquent\ExpenseCategoryRepository;
use App\Services\BranchContext;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpenseCategoryRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_find_by_id_returns_the_matching_category(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);

        $category = ExpenseCategory::factory()->create(['tenant_id' => $tenant->id]);

        $repository = new ExpenseCategoryRepository(new ExpenseCategory);

        $this->assertTrue($repository->findById($category->id)->is($category));
    }

    public function test_get_all_returns_categories_ordered_by_name(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);

        ExpenseCategory::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Utilities']);
        ExpenseCategory::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Marketing']);

        $repository = new ExpenseCategoryRepository(new ExpenseCategory);

        $results = $repository->getAll();

        $this->assertSame('Marketing', $results->first()->name);
    }

    public function test_get_active_excludes_inactive_categories(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);

        ExpenseCategory::factory()->create(['tenant_id' => $tenant->id]);
        ExpenseCategory::factory()->inactive()->create(['tenant_id' => $tenant->id]);

        $repository = new ExpenseCategoryRepository(new ExpenseCategory);

        $this->assertCount(1, $repository->getActive());
    }

    public function test_create_persists_a_new_category(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);

        $repository = new ExpenseCategoryRepository(new ExpenseCategory);

        $created = $repository->create(['tenant_id' => $tenant->id, 'name' => 'Rent']);

        $this->assertDatabaseHas('expense_categories', ['id' => $created->id, 'name' => 'Rent']);
    }

    public function test_update_persists_changes(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);

        $category = ExpenseCategory::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Original']);

        $repository = new ExpenseCategoryRepository(new ExpenseCategory);
        $repository->update($category, ['name' => 'Updated']);

        $this->assertSame('Updated', $category->fresh()->name);
    }

    public function test_delete_soft_deletes_the_category(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);

        $category = ExpenseCategory::factory()->create(['tenant_id' => $tenant->id]);

        $repository = new ExpenseCategoryRepository(new ExpenseCategory);

        $this->assertTrue($repository->delete($category));
        $this->assertSoftDeleted('expense_categories', ['id' => $category->id]);
    }
}
