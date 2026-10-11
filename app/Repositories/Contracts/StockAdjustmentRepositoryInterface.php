<?php

namespace App\Repositories\Contracts;

use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\StockAdjustment;
use Illuminate\Database\Eloquent\Collection;

interface StockAdjustmentRepositoryInterface
{
    /** @param array<string, mixed> $data */
    public function create(array $data): StockAdjustment;

    /** @return Collection<int, StockAdjustment> */
    public function getForProduct(Product $product): Collection;

    /** @return Collection<int, StockAdjustment> */
    public function getForBill(int $billId, StockMovementType $type): Collection;

    public function existsForBill(int $billId, StockMovementType $type): bool;

    /**
     * Last physical count date and the net bill-usage movement since that
     * count (negative means stock was estimated as consumed), per product.
     *
     * @param  array<int, int>  $productIds
     * @return array<int, array{last_counted_at: ?string, usage_since_count: string}>
     */
    public function getCountStats(array $productIds): array;
}
