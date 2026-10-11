<?php

namespace App\Services;

use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\StockPurchase;
use App\Repositories\Contracts\StockPurchaseRepositoryInterface;
use Illuminate\Support\Facades\DB;

class StockPurchaseService
{
    public function __construct(
        private StockPurchaseRepositoryInterface $purchaseRepository,
        private InventoryService $inventoryService,
        private TenantContext $tenantContext,
        private BranchContext $branchContext,
    ) {}

    /** @param array<string, mixed> $data */
    public function recordPurchase(Product $product, array $data, int $userId): StockPurchase
    {
        return DB::transaction(function () use ($product, $data, $userId): StockPurchase {
            $purchase = $this->purchaseRepository->create([
                'tenant_id' => $this->tenantContext->get()->id,
                'branch_id' => $this->branchContext->get()->id,
                'product_id' => $product->id,
                'created_by' => $userId,
                'quantity' => $data['quantity'],
                'unit_cost' => $data['unit_cost'] ?? 0,
                'supplier_name' => $data['supplier_name'] ?? null,
                'invoice_no' => $data['invoice_no'] ?? null,
                'purchased_at' => $data['purchased_at'],
            ]);

            $this->inventoryService->adjustStock(
                $product,
                (float) $data['quantity'],
                $purchase->supplier_name ? "Purchase from {$purchase->supplier_name}" : 'Purchase',
                $userId,
                StockMovementType::Purchase,
                purchaseId: $purchase->id,
            );

            return $purchase;
        });
    }
}
