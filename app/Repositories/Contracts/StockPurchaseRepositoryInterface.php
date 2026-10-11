<?php

namespace App\Repositories\Contracts;

use App\Models\StockPurchase;
use Illuminate\Database\Eloquent\Collection;

interface StockPurchaseRepositoryInterface
{
    /** @param array<string, mixed> $data */
    public function create(array $data): StockPurchase;

    /** @return Collection<int, StockPurchase> */
    public function getRecent(int $limit = 100): Collection;
}
