<?php

namespace Tests\Integration\Expenses;

use App\Models\Branch;
use App\Models\Tenant;
use App\Models\User;
use App\Repositories\Eloquent\ExpenseCategoryRepository;
use App\Repositories\Eloquent\ExpenseRepository;
use App\Services\BranchContext;
use App\Services\ExpenseService;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExpenseReceiptStorageTest extends TestCase
{
    use RefreshDatabase;

    public function test_uploaded_receipt_is_persisted_to_the_local_disk(): void
    {
        Storage::fake('local');

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

        $receipt = UploadedFile::fake()->image('receipt.jpg');

        $expense = $service->create([
            'description' => 'Product restock',
            'amount' => 425,
            'expense_date' => '2026-08-01',
            'receipt' => $receipt,
            'created_by' => $owner->id,
        ]);

        Storage::disk('local')->assertExists($expense->receipt_path);
        $this->assertStringStartsWith('receipts/', $expense->receipt_path);
    }

    public function test_updating_an_expense_with_a_new_receipt_replaces_the_stored_path(): void
    {
        Storage::fake('local');

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

        $expense = $service->create([
            'description' => 'Initial',
            'amount' => 100,
            'expense_date' => '2026-08-01',
            'receipt' => UploadedFile::fake()->create('first.pdf', 50),
            'created_by' => $owner->id,
        ]);

        $originalPath = $expense->receipt_path;

        $updated = $service->update($expense, [
            'receipt' => UploadedFile::fake()->create('second.pdf', 50),
        ]);

        Storage::disk('local')->assertExists($originalPath);
        Storage::disk('local')->assertExists($updated->receipt_path);
        $this->assertNotSame($originalPath, $updated->receipt_path);
    }
}
