<?php

namespace App\Repositories\Eloquent;

use App\Models\Bill;
use App\Models\BillLineItem;
use App\Repositories\Contracts\BillLineItemRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

class BillLineItemRepository implements BillLineItemRepositoryInterface
{
    public function __construct(private BillLineItem $model) {}

    /** @return Collection<int, BillLineItem> */
    public function getPaidBetween(Carbon $from, Carbon $to): Collection
    {
        return $this->model
            ->whereHas('bill', function (Builder $query) use ($from, $to): void {
                $query->where('status', Bill::StatusPaid)
                    ->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()]);
            })
            ->with(['bill', 'staffProfile', 'referredByStaffProfile'])
            ->get();
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): BillLineItem
    {
        return $this->model->create($data);
    }

    public function delete(BillLineItem $lineItem): bool
    {
        return (bool) $lineItem->delete();
    }

    /** @param array<string, mixed> $data */
    public function update(BillLineItem $lineItem, array $data): BillLineItem
    {
        $lineItem->update($data);

        return $lineItem;
    }
}
