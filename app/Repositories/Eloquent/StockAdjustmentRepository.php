<?php

namespace App\Repositories\Eloquent;

use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\StockAdjustment;
use App\Repositories\Contracts\StockAdjustmentRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class StockAdjustmentRepository implements StockAdjustmentRepositoryInterface
{
    public function __construct(private StockAdjustment $model) {}

    /** @param array<string, mixed> $data */
    public function create(array $data): StockAdjustment
    {
        return $this->model->create($data);
    }

    /** @return Collection<int, StockAdjustment> */
    public function getForProduct(Product $product): Collection
    {
        return $this->model
            ->where('product_id', $product->id)
            ->with('adjustedBy')
            ->latest('id')
            ->get();
    }

    /** @return Collection<int, StockAdjustment> */
    public function getForBill(int $billId, StockMovementType $type): Collection
    {
        return $this->model
            ->where('bill_id', $billId)
            ->where('type', $type->value)
            ->get();
    }

    public function existsForBill(int $billId, StockMovementType $type): bool
    {
        return $this->model
            ->where('bill_id', $billId)
            ->where('type', $type->value)
            ->exists();
    }

    /**
     * @param  array<int, int>  $productIds
     * @return array<int, array{last_counted_at: ?string, usage_since_count: string}>
     */
    public function getCountStats(array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }

        $movements = $this->model
            ->whereIn('product_id', $productIds)
            ->whereIn('type', [
                StockMovementType::CountCorrection->value,
                StockMovementType::Usage->value,
                StockMovementType::UsageReversal->value,
            ])
            ->orderBy('id')
            ->get(['id', 'product_id', 'type', 'quantity_delta', 'created_at']);

        $stats = [];

        foreach ($productIds as $productId) {
            $stats[$productId] = ['last_counted_at' => null, 'usage_since_count' => '0.00'];
        }

        foreach ($movements as $movement) {
            if ($movement->type === StockMovementType::CountCorrection) {
                $stats[$movement->product_id] = [
                    'last_counted_at' => $movement->created_at->toDateTimeString(),
                    'usage_since_count' => '0.00',
                ];

                continue;
            }

            $stats[$movement->product_id]['usage_since_count'] = bcadd(
                $stats[$movement->product_id]['usage_since_count'],
                (string) $movement->quantity_delta,
                2,
            );
        }

        return $stats;
    }
}
