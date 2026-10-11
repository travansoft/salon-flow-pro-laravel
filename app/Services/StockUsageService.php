<?php

namespace App\Services;

use App\Enums\StockMovementType;
use App\Models\Bill;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Repositories\Contracts\ServiceProductUsageRepositoryInterface;
use App\Repositories\Contracts\StockAdjustmentRepositoryInterface;

/**
 * Estimates product consumption from the services on a bill. The figures are
 * a calculation margin only; physical counts reset them.
 */
class StockUsageService
{
    public function __construct(
        private ServiceProductUsageRepositoryInterface $usageRepository,
        private StockAdjustmentRepositoryInterface $adjustmentRepository,
        private ProductRepositoryInterface $productRepository,
        private InventoryService $inventoryService,
    ) {}

    public function consumeForBill(Bill $bill): void
    {
        $serviceIds = $bill->lineItems->pluck('service_id')->filter()->unique()->values()->all();

        if ($serviceIds === []) {
            return;
        }

        $usagesByService = $this->usageRepository->getForServices($serviceIds)->groupBy('service_id');

        $consumedByProduct = [];

        foreach ($bill->lineItems as $lineItem) {
            if (! $lineItem->service_id) {
                continue;
            }

            foreach ($usagesByService->get($lineItem->service_id, []) as $usage) {
                $amount = bcmul((string) $usage->quantity_used, (string) $lineItem->quantity, 2);

                $consumedByProduct[$usage->product_id] = bcadd($consumedByProduct[$usage->product_id] ?? '0', $amount, 2);
            }
        }

        foreach ($consumedByProduct as $productId => $amount) {
            $product = $this->productRepository->findById($productId);

            if (! $product || bccomp($amount, '0', 2) <= 0) {
                continue;
            }

            $this->inventoryService->adjustStock(
                $product,
                -(float) $amount,
                "Used in bill {$bill->bill_number}",
                $bill->created_by,
                StockMovementType::Usage,
                billId: $bill->id,
            );
        }
    }

    public function restoreForBill(Bill $bill): void
    {
        if ($this->adjustmentRepository->existsForBill($bill->id, StockMovementType::UsageReversal)) {
            return;
        }

        foreach ($this->adjustmentRepository->getForBill($bill->id, StockMovementType::Usage) as $movement) {
            $product = $this->productRepository->findById($movement->product_id);

            if (! $product) {
                continue;
            }

            $this->inventoryService->adjustStock(
                $product,
                abs((float) $movement->quantity_delta),
                "Bill {$bill->bill_number} cancelled",
                $bill->created_by,
                StockMovementType::UsageReversal,
                billId: $bill->id,
            );
        }
    }
}
