<?php

namespace App\Repositories\Eloquent;

use App\Models\Bill;
use App\Repositories\Contracts\BillRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class BillRepository implements BillRepositoryInterface
{
    public function __construct(private Bill $model) {}

    public function findById(int $id): ?Bill
    {
        return $this->model->find($id);
    }

    /**
     * Locks the highest existing bill number for this tenant, branch, and
     * financial year so concurrent bill creation cannot allocate the same
     * sequential number twice. Must be called from within a transaction.
     */
    public function nextBillNumber(int $tenantId, int $branchId, string $financialYear): int
    {
        $lastNumber = DB::table('bills')
            ->where('tenant_id', $tenantId)
            ->where('branch_id', $branchId)
            ->where('financial_year', $financialYear)
            ->orderByDesc('bill_number')
            ->lockForUpdate()
            ->value('bill_number');

        return ($lastNumber ?? 0) + 1;
    }

    /** @return Collection<int, Bill> */
    public function search(string $fromDate, string $toDate, ?string $clientName, ?string $clientPhone): Collection
    {
        return $this->model->whereDate('created_at', '>=', $fromDate)
            ->whereDate('created_at', '<=', $toDate)
            ->when($clientName, fn (Builder $query, string $clientName) => $query->whereHas(
                'client',
                fn (Builder $clientQuery) => $clientQuery->whereRaw('LOWER(name) LIKE ?', ['%'.strtolower($clientName).'%'])
            ))
            ->when($clientPhone, fn (Builder $query, string $clientPhone) => $query->whereHas(
                'client',
                fn (Builder $clientQuery) => $clientQuery->where('phone', 'LIKE', '%'.$clientPhone.'%')
            ))
            ->with(['client', 'payments', 'createdBy'])
            ->orderBy('bill_number')
            ->get();
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): Bill
    {
        return $this->model->create($data);
    }

    /** @param array<string, mixed> $data */
    public function update(Bill $bill, array $data): Bill
    {
        $bill->update($data);

        return $bill;
    }
}
