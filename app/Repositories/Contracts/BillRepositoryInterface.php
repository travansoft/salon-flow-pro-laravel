<?php

namespace App\Repositories\Contracts;

use App\Models\Bill;
use Illuminate\Database\Eloquent\Collection;

interface BillRepositoryInterface
{
    public function findById(int $id): ?Bill;

    public function nextBillNumber(int $tenantId, string $financialYear): int;

    /** @return Collection<int, Bill> */
    public function getForDate(string $date): Collection;

    /** @param array<string, mixed> $data */
    public function create(array $data): Bill;

    /** @param array<string, mixed> $data */
    public function update(Bill $bill, array $data): Bill;
}
