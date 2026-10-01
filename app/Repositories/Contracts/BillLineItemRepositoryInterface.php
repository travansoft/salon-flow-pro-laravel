<?php

namespace App\Repositories\Contracts;

use App\Models\BillLineItem;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

interface BillLineItemRepositoryInterface
{
    /**
     * Line items of paid bills created in the range, with their bill,
     * servicing staff and referrer loaded, for incentive credit.
     *
     * @return Collection<int, BillLineItem>
     */
    public function getPaidBetween(Carbon $from, Carbon $to): Collection;

    /** @param array<string, mixed> $data */
    public function update(BillLineItem $lineItem, array $data): BillLineItem;
}
