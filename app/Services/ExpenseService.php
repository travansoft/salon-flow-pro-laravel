<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Repositories\Contracts\ExpenseCategoryRepositoryInterface;
use App\Repositories\Contracts\ExpenseRepositoryInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class ExpenseService
{
    public function __construct(
        private ExpenseRepositoryInterface $expenseRepository,
        private ExpenseCategoryRepositoryInterface $expenseCategoryRepository,
        private TenantContext $tenantContext,
        private BranchContext $branchContext,
    ) {}

    /** @param array<string, mixed> $data */
    public function create(array $data): Expense
    {
        $tenant = $this->tenantContext->get();
        $branch = $this->branchContext->get();

        return $this->expenseRepository->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'category_id' => $data['category_id'] ?? null,
            'description' => $data['description'],
            'amount' => $data['amount'],
            'is_recurring' => $data['is_recurring'] ?? false,
            'recurrence_interval' => ($data['is_recurring'] ?? false) ? ($data['recurrence_interval'] ?? null) : null,
            'expense_date' => $data['expense_date'],
            'receipt_path' => $this->storeReceipt($data['receipt'] ?? null),
            'created_by' => $data['created_by'],
        ]);
    }

    /** @param array<string, mixed> $data */
    public function update(Expense $expense, array $data): Expense
    {
        $isRecurring = array_key_exists('is_recurring', $data) ? $data['is_recurring'] : $expense->is_recurring;

        $receiptPath = $expense->receipt_path;

        if (array_key_exists('receipt', $data) && $data['receipt'] instanceof UploadedFile) {
            $receiptPath = $this->storeReceipt($data['receipt']);
        }

        return $this->expenseRepository->update($expense, [
            'category_id' => array_key_exists('category_id', $data) ? $data['category_id'] : $expense->category_id,
            'description' => $data['description'] ?? $expense->description,
            'amount' => $data['amount'] ?? $expense->amount,
            'is_recurring' => $isRecurring,
            'recurrence_interval' => $isRecurring
                ? (array_key_exists('recurrence_interval', $data) ? $data['recurrence_interval'] : $expense->recurrence_interval)
                : null,
            'expense_date' => $data['expense_date'] ?? $expense->expense_date,
            'receipt_path' => $receiptPath,
        ]);
    }

    public function delete(Expense $expense): bool
    {
        return $this->expenseRepository->delete($expense);
    }

    public function totalForMonth(Carbon $month): string
    {
        $from = $month->copy()->startOfMonth();
        $to = $month->copy()->endOfMonth();

        return $this->expenseRepository->getBetweenDates($from, $to)
            ->reduce(fn (string $carry, Expense $expense): string => bcadd($carry, (string) $expense->amount, 2), '0.00');
    }

    /** @param array<string, mixed> $data */
    public function createCategory(array $data): ExpenseCategory
    {
        return $this->expenseCategoryRepository->create([
            ...$data,
            'tenant_id' => $this->tenantContext->get()->id,
        ]);
    }

    /** @param array<string, mixed> $data */
    public function updateCategory(ExpenseCategory $category, array $data): ExpenseCategory
    {
        return $this->expenseCategoryRepository->update($category, $data);
    }

    public function deleteCategory(ExpenseCategory $category): bool
    {
        return $this->expenseCategoryRepository->delete($category);
    }

    private function storeReceipt(?UploadedFile $receipt): ?string
    {
        if (! $receipt) {
            return null;
        }

        return Storage::disk('local')->putFile('receipts', $receipt);
    }
}
