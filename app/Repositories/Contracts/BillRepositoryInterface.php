<?php

namespace App\Repositories\Contracts;

use App\Models\Bill;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

interface BillRepositoryInterface
{
    public function findById(int $id): ?Bill;

    public function nextBillNumber(int $tenantId, int $branchId, string $financialYear): int;

    /** @return Collection<int, Bill> */
    public function search(string $fromDate, string $toDate, ?string $clientName, ?string $clientPhone): Collection;

    /**
     * Non-void bills created within the given range, for reporting.
     * Filtered by the currently resolved branch via BranchScope unless the
     * caller has explicitly bypassed BranchContext (consolidated reports).
     *
     * @return Collection<int, Bill>
     */
    public function forDateRange(Carbon $from, Carbon $to): Collection;

    /** Sum of non-void bill totals on a single date, for reporting trends. */
    public function totalForDate(Carbon $date): string;

    /** @param array<string, mixed> $data */
    public function create(array $data): Bill;

    /** @param array<string, mixed> $data */
    public function update(Bill $bill, array $data): Bill;
}
