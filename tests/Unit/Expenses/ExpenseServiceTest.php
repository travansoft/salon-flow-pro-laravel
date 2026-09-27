<?php

namespace Tests\Unit\Expenses;

use App\Models\Branch;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Tenant;
use App\Models\User;
use App\Repositories\Contracts\ExpenseCategoryRepositoryInterface;
use App\Repositories\Contracts\ExpenseRepositoryInterface;
use App\Services\BranchContext;
use App\Services\ExpenseService;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class ExpenseServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_persists_an_expense_for_the_current_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $tenantContext = app(TenantContext::class);
        $tenantContext->set($tenant);
        $branchContext = app(BranchContext::class);
        $branchContext->set(Branch::factory()->create(['tenant_id' => $tenant->id]));
        $owner = User::factory()->for($tenant)->create();

        $expenseRepository = Mockery::mock(ExpenseRepositoryInterface::class);
        $expenseRepository->shouldReceive('create')
            ->once()
            ->with(Mockery::on(fn (array $data) => $data['tenant_id'] === $tenant->id && $data['description'] === 'Monthly rent'))
            ->andReturnUsing(fn (array $data) => Expense::create($data));

        $categoryRepository = Mockery::mock(ExpenseCategoryRepositoryInterface::class);

        $service = new ExpenseService($expenseRepository, $categoryRepository, $tenantContext, $branchContext);

        $created = $service->create([
            'description' => 'Monthly rent',
            'amount' => 5000,
            'expense_date' => '2026-08-01',
            'created_by' => $owner->id,
        ]);

        $this->assertSame('Monthly rent', $created->description);
        $this->assertFalse($created->is_recurring);
    }

    public function test_create_ignores_recurrence_interval_when_not_recurring(): void
    {
        $tenant = Tenant::factory()->create();
        $tenantContext = app(TenantContext::class);
        $tenantContext->set($tenant);
        $branchContext = app(BranchContext::class);
        $branchContext->set(Branch::factory()->create(['tenant_id' => $tenant->id]));
        $owner = User::factory()->for($tenant)->create();

        $expenseRepository = Mockery::mock(ExpenseRepositoryInterface::class);
        $expenseRepository->shouldReceive('create')
            ->once()
            ->andReturnUsing(fn (array $data) => Expense::create($data));

        $categoryRepository = Mockery::mock(ExpenseCategoryRepositoryInterface::class);

        $service = new ExpenseService($expenseRepository, $categoryRepository, $tenantContext, $branchContext);

        $created = $service->create([
            'description' => 'One-off purchase',
            'amount' => 199,
            'is_recurring' => false,
            'recurrence_interval' => 'monthly',
            'expense_date' => '2026-08-01',
            'created_by' => $owner->id,
        ]);

        $this->assertNull($created->recurrence_interval);
    }

    public function test_create_stores_an_uploaded_receipt(): void
    {
        Storage::fake('local');

        $tenant = Tenant::factory()->create();
        $tenantContext = app(TenantContext::class);
        $tenantContext->set($tenant);
        $branchContext = app(BranchContext::class);
        $branchContext->set(Branch::factory()->create(['tenant_id' => $tenant->id]));
        $owner = User::factory()->for($tenant)->create();

        $expenseRepository = Mockery::mock(ExpenseRepositoryInterface::class);
        $expenseRepository->shouldReceive('create')
            ->once()
            ->andReturnUsing(fn (array $data) => Expense::create($data));

        $categoryRepository = Mockery::mock(ExpenseCategoryRepositoryInterface::class);

        $service = new ExpenseService($expenseRepository, $categoryRepository, $tenantContext, $branchContext);

        $receipt = UploadedFile::fake()->create('receipt.pdf', 100);

        $created = $service->create([
            'description' => 'Supplies',
            'amount' => 350,
            'expense_date' => '2026-08-01',
            'receipt' => $receipt,
            'created_by' => $owner->id,
        ]);

        $this->assertNotNull($created->receipt_path);
        Storage::disk('local')->assertExists($created->receipt_path);
    }

    public function test_update_clears_recurrence_interval_when_marked_non_recurring(): void
    {
        $tenant = Tenant::factory()->create();
        $tenantContext = app(TenantContext::class);
        $tenantContext->set($tenant);
        $branchContext = app(BranchContext::class);
        $branchContext->set(Branch::factory()->create(['tenant_id' => $tenant->id]));

        $expense = Expense::factory()->recurring('monthly')->create(['tenant_id' => $tenant->id]);

        $expenseRepository = Mockery::mock(ExpenseRepositoryInterface::class);
        $expenseRepository->shouldReceive('update')
            ->once()
            ->andReturnUsing(function ($model, array $data) {
                $model->update($data);

                return $model;
            });

        $categoryRepository = Mockery::mock(ExpenseCategoryRepositoryInterface::class);

        $service = new ExpenseService($expenseRepository, $categoryRepository, $tenantContext, $branchContext);

        $service->update($expense, ['is_recurring' => false]);

        $this->assertFalse($expense->fresh()->is_recurring);
        $this->assertNull($expense->fresh()->recurrence_interval);
    }

    public function test_delete_delegates_to_repository(): void
    {
        $tenant = Tenant::factory()->create();
        $tenantContext = app(TenantContext::class);
        $tenantContext->set($tenant);
        $branchContext = app(BranchContext::class);
        $branchContext->set(Branch::factory()->create(['tenant_id' => $tenant->id]));

        $expense = Expense::factory()->create(['tenant_id' => $tenant->id]);

        $expenseRepository = Mockery::mock(ExpenseRepositoryInterface::class);
        $expenseRepository->shouldReceive('delete')->once()->with($expense)->andReturn(true);

        $categoryRepository = Mockery::mock(ExpenseCategoryRepositoryInterface::class);

        $service = new ExpenseService($expenseRepository, $categoryRepository, $tenantContext, $branchContext);

        $this->assertTrue($service->delete($expense));
    }

    public function test_total_for_month_sums_only_expenses_within_the_month(): void
    {
        $tenant = Tenant::factory()->create();
        $tenantContext = app(TenantContext::class);
        $tenantContext->set($tenant);
        $branchContext = app(BranchContext::class);
        $branchContext->set(Branch::factory()->create(['tenant_id' => $tenant->id]));

        Expense::factory()->create(['tenant_id' => $tenant->id, 'amount' => 100, 'expense_date' => '2026-08-05']);
        Expense::factory()->create(['tenant_id' => $tenant->id, 'amount' => 250.50, 'expense_date' => '2026-08-20']);
        Expense::factory()->create(['tenant_id' => $tenant->id, 'amount' => 999, 'expense_date' => '2026-07-31']);
        Expense::factory()->create(['tenant_id' => $tenant->id, 'amount' => 999, 'expense_date' => '2026-09-01']);

        $expenseRepository = app(ExpenseRepositoryInterface::class);
        $categoryRepository = Mockery::mock(ExpenseCategoryRepositoryInterface::class);

        $service = new ExpenseService($expenseRepository, $categoryRepository, $tenantContext, $branchContext);

        $total = $service->totalForMonth(Carbon::parse('2026-08-15'));

        $this->assertSame('350.50', $total);
    }

    public function test_create_category_scopes_to_current_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $tenantContext = app(TenantContext::class);
        $tenantContext->set($tenant);
        $branchContext = app(BranchContext::class);
        $branchContext->set(Branch::factory()->create(['tenant_id' => $tenant->id]));

        $expenseRepository = Mockery::mock(ExpenseRepositoryInterface::class);
        $categoryRepository = Mockery::mock(ExpenseCategoryRepositoryInterface::class);
        $categoryRepository->shouldReceive('create')
            ->once()
            ->with(Mockery::on(fn (array $data) => $data['tenant_id'] === $tenant->id && $data['name'] === 'Rent'))
            ->andReturnUsing(fn (array $data) => ExpenseCategory::create($data));

        $service = new ExpenseService($expenseRepository, $categoryRepository, $tenantContext, $branchContext);

        $created = $service->createCategory(['name' => 'Rent']);

        $this->assertSame('Rent', $created->name);
    }

    public function test_update_category_delegates_to_repository(): void
    {
        $tenant = Tenant::factory()->create();
        $tenantContext = app(TenantContext::class);
        $tenantContext->set($tenant);
        $branchContext = app(BranchContext::class);
        $branchContext->set(Branch::factory()->create(['tenant_id' => $tenant->id]));

        $category = ExpenseCategory::factory()->create(['tenant_id' => $tenant->id]);

        $expenseRepository = Mockery::mock(ExpenseRepositoryInterface::class);
        $categoryRepository = Mockery::mock(ExpenseCategoryRepositoryInterface::class);
        $categoryRepository->shouldReceive('update')
            ->once()
            ->andReturnUsing(function ($model, array $data) {
                $model->update($data);

                return $model;
            });

        $service = new ExpenseService($expenseRepository, $categoryRepository, $tenantContext, $branchContext);

        $service->updateCategory($category, ['name' => 'Renamed']);

        $this->assertSame('Renamed', $category->fresh()->name);
    }

    public function test_delete_category_delegates_to_repository(): void
    {
        $tenant = Tenant::factory()->create();
        $tenantContext = app(TenantContext::class);
        $tenantContext->set($tenant);
        $branchContext = app(BranchContext::class);
        $branchContext->set(Branch::factory()->create(['tenant_id' => $tenant->id]));

        $category = ExpenseCategory::factory()->create(['tenant_id' => $tenant->id]);

        $expenseRepository = Mockery::mock(ExpenseRepositoryInterface::class);
        $categoryRepository = Mockery::mock(ExpenseCategoryRepositoryInterface::class);
        $categoryRepository->shouldReceive('delete')->once()->with($category)->andReturn(true);

        $service = new ExpenseService($expenseRepository, $categoryRepository, $tenantContext, $branchContext);

        $this->assertTrue($service->deleteCategory($category));
    }
}
