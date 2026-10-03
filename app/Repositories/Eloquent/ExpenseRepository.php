<?php

namespace App\Repositories\Eloquent;

use App\Models\Expense;
use App\Repositories\Contracts\ExpenseRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection as SupportCollection;

class ExpenseRepository implements ExpenseRepositoryInterface
{
    public function __construct(private Expense $model) {}

    public function findById(int $id): ?Expense
    {
        return $this->model->find($id);
    }

    /** @return Collection<int, Expense> */
    public function getAll(): Collection
    {
        return $this->model->with('category')->orderByDesc('expense_date')->get();
    }

    /** @return Collection<int, Expense> */
    public function getBetweenDates(Carbon $from, Carbon $to): Collection
    {
        return $this->model->betweenDates($from, $to)->with('category')->orderByDesc('expense_date')->get();
    }

    /** @return Collection<int, Expense> */
    public function getFiltered(Carbon $from, Carbon $to, array $filters): Collection
    {
        $query = $this->model->betweenDates($from, $to)->with('category');

        $categoryId = $filters['category_id'] ?? null;

        if ($categoryId === 'none') {
            $query->whereNull('category_id');
        }

        if ($categoryId !== null && $categoryId !== 'none' && $categoryId !== '') {
            $query->where('category_id', (int) $categoryId);
        }

        if (! empty($filters['payment_method'])) {
            $query->where('payment_method', $filters['payment_method']);
        }

        if (! empty($filters['search'])) {
            $term = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $filters['search']);

            $query->whereRaw("LOWER(description) LIKE ? ESCAPE '\\'", ['%'.mb_strtolower($term).'%']);
        }

        return $query->orderByDesc('expense_date')->orderByDesc('id')->get();
    }

    /** @return SupportCollection<int, object{category_id: ?int, name: ?string, expense_count: int, total: string}> */
    public function totalsByCategoryBetween(Carbon $from, Carbon $to): SupportCollection
    {
        return $this->model
            ->betweenDates($from, $to)
            ->leftJoin('expense_categories', 'expense_categories.id', '=', 'expenses.category_id')
            ->selectRaw('expenses.category_id, expense_categories.name, COUNT(*) as expense_count, SUM(expenses.amount) as total')
            ->groupBy('expenses.category_id', 'expense_categories.name')
            ->orderByDesc('total')
            ->toBase()
            ->get();
    }

    /** @return array<string, string> */
    public function totalsByMethodBefore(Carbon $date): array
    {
        return $this->model
            ->where('expense_date', '<', $date->copy()->startOfDay())
            ->selectRaw('payment_method, SUM(amount) as total')
            ->groupBy('payment_method')
            ->pluck('total', 'payment_method')
            ->map(fn ($total): string => number_format((float) $total, 2, '.', ''))
            ->all();
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): Expense
    {
        return $this->model->create($data);
    }

    /** @param array<string, mixed> $data */
    public function update(Expense $expense, array $data): Expense
    {
        $expense->update($data);

        return $expense;
    }

    public function delete(Expense $expense): bool
    {
        return (bool) $expense->delete();
    }
}
