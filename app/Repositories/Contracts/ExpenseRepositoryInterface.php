<?php

namespace App\Repositories\Contracts;

use App\Models\Expense;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection as SupportCollection;

interface ExpenseRepositoryInterface
{
    public function findById(int $id): ?Expense;

    /** @return Collection<int, Expense> */
    public function getAll(): Collection;

    /** @return Collection<int, Expense> */
    public function getBetweenDates(Carbon $from, Carbon $to): Collection;

    /**
     * @param  array{category_id?: int|string|null, payment_method?: ?string, search?: ?string}  $filters
     * @return Collection<int, Expense>
     */
    public function getFiltered(Carbon $from, Carbon $to, array $filters): Collection;

    /** @return SupportCollection<int, object{category_id: ?int, name: ?string, expense_count: int, total: string}> */
    public function totalsByCategoryBetween(Carbon $from, Carbon $to): SupportCollection;

    /** @return array<string, string> Expense totals keyed by payment method, for everything dated before the date. */
    public function totalsByMethodBefore(Carbon $date): array;

    /** @param array<string, mixed> $data */
    public function create(array $data): Expense;

    /** @param array<string, mixed> $data */
    public function update(Expense $expense, array $data): Expense;

    public function delete(Expense $expense): bool;
}
