<?php

namespace App\Repositories\Eloquent;

use App\Models\Bill;
use App\Models\BillPayment;
use App\Models\BillRefund;
use App\Repositories\Contracts\BillRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
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

    /** @return Collection<int, Bill> */
    public function forDateRange(Carbon $from, Carbon $to): Collection
    {
        return $this->model->where('status', '!=', Bill::StatusVoid)
            ->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->with(['lineItems.service', 'lineItems.comboService', 'lineItems.staffProfile', 'lineItems.referredByStaffProfile', 'payments'])
            ->get();
    }

    /** @return Collection<int, Bill> */
    public function forGstReport(Carbon $from, Carbon $to): Collection
    {
        return $this->model->where('status', '!=', Bill::StatusVoid)
            ->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->with(['client', 'branch', 'lineItems'])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();
    }

    public function totalForDate(Carbon $date): string
    {
        return (string) $this->model->where('status', '!=', Bill::StatusVoid)
            ->whereDate('created_at', $date)
            ->sum('total');
    }

    /** @return Collection<int, BillPayment> */
    public function paymentsBetween(Carbon $from, Carbon $to): Collection
    {
        return BillPayment::query()
            ->whereHas('bill', fn (Builder $query) => $query->where('status', '!=', Bill::StatusVoid))
            ->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->with(['bill.client', 'bill.branch'])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();
    }

    /** @return Collection<int, BillRefund> */
    public function refundsBetween(Carbon $from, Carbon $to): Collection
    {
        return BillRefund::query()
            ->whereHas('bill', fn (Builder $query) => $query->where('status', '!=', Bill::StatusVoid))
            ->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->with(['bill.client', 'bill.branch'])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();
    }

    /** @return array<string, string> */
    public function paymentTotalsByMethodBefore(Carbon $date): array
    {
        return $this->totalsByMethod(BillPayment::query(), $date);
    }

    /** @return array<string, string> */
    public function refundTotalsByMethodBefore(Carbon $date): array
    {
        return $this->totalsByMethod(BillRefund::query(), $date);
    }

    /**
     * @param  Builder<BillPayment>|Builder<BillRefund>  $query
     * @return array<string, string>
     */
    private function totalsByMethod(Builder $query, Carbon $date): array
    {
        return $query
            ->whereHas('bill', fn (Builder $billQuery) => $billQuery->where('status', '!=', Bill::StatusVoid))
            ->where('created_at', '<', $date->copy()->startOfDay())
            ->selectRaw('method, SUM(amount) as total')
            ->groupBy('method')
            ->pluck('total', 'method')
            ->map(fn ($total): string => number_format((float) $total, 2, '.', ''))
            ->all();
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
