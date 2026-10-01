<?php

namespace App\Repositories\Contracts;

use App\Models\Expense;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

interface ExpenseRepositoryInterface
{
    public function findById(int $id): ?Expense;

    /** @return Collection<int, Expense> */
    public function getAll(): Collection;

    /** @return Collection<int, Expense> */
    public function getBetweenDates(Carbon $from, Carbon $to): Collection;

    /** @return array<string, string> Expense totals keyed by payment method, for everything dated before the date. */
    public function totalsByMethodBefore(Carbon $date): array;

    /** @param array<string, mixed> $data */
    public function create(array $data): Expense;

    /** @param array<string, mixed> $data */
    public function update(Expense $expense, array $data): Expense;

    public function delete(Expense $expense): bool;
}
