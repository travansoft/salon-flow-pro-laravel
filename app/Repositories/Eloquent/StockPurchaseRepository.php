<?php

namespace App\Repositories\Eloquent;

use App\Models\StockPurchase;
use App\Repositories\Contracts\StockPurchaseRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class StockPurchaseRepository implements StockPurchaseRepositoryInterface
{
    public function __construct(private StockPurchase $model) {}

    /** @param array<string, mixed> $data */
    public function create(array $data): StockPurchase
    {
        return $this->model->create($data);
    }

    /** @return Collection<int, StockPurchase> */
    public function getRecent(int $limit = 100): Collection
    {
        return $this->model
            ->with(['product', 'createdBy'])
            ->orderByDesc('purchased_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }
}
