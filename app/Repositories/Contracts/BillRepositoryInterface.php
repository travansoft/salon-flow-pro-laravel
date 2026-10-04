<?php

namespace App\Repositories\Contracts;

use App\Models\Bill;
use App\Models\BillPayment;
use App\Models\BillRefund;
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

    /**
     * Non-void bills in the range with the relations the GST report needs.
     *
     * @return Collection<int, Bill>
     */
    public function forGstReport(Carbon $from, Carbon $to): Collection;

    /** Sum of non-void bill totals on a single date, for reporting trends. */
    public function totalForDate(Carbon $date): string;

    /**
     * Payments received between two dates on non-void bills, oldest first.
     *
     * @return Collection<int, BillPayment>
     */
    public function paymentsBetween(Carbon $from, Carbon $to): Collection;

    /**
     * Refunds paid out between two dates on non-void bills, oldest first.
     *
     * @return Collection<int, BillRefund>
     */
    public function refundsBetween(Carbon $from, Carbon $to): Collection;

    /** @return array<string, string> Payment totals keyed by method, for everything received before the date. */
    public function paymentTotalsByMethodBefore(Carbon $date): array;

    /** @return array<string, string> Refund totals keyed by method, for everything paid out before the date. */
    public function refundTotalsByMethodBefore(Carbon $date): array;

    /** @param array<string, mixed> $data */
    public function create(array $data): Bill;

    /** @param array<string, mixed> $data */
    public function update(Bill $bill, array $data): Bill;

    /** @return Collection<int, Bill> Recent non-void bills not yet attached to any bridal engagement. */
    public function getAttachableToEngagement(int $limit = 100): Collection;
}
