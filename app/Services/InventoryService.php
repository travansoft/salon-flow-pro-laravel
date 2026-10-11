<?php

namespace App\Services;

use App\Enums\StockMovementType;
use App\Models\InventoryCategory;
use App\Models\Product;
use App\Repositories\Contracts\InventoryCategoryRepositoryInterface;
use App\Repositories\Contracts\ProductRepositoryInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class InventoryService
{
    public function __construct(
        private ProductRepositoryInterface $productRepository,
        private InventoryCategoryRepositoryInterface $categoryRepository,
        private TenantContext $tenantContext,
        private BranchContext $branchContext,
    ) {}

    /** @param array<string, mixed> $data */
    public function createProduct(array $data): Product
    {
        return $this->productRepository->create([
            'tenant_id' => $this->tenantContext->get()->id,
            'branch_id' => $this->branchContext->get()->id,
            'category_id' => $data['category_id'] ?? null,
            'name' => $data['name'],
            'sku' => $data['sku'] ?? null,
            'quantity_on_hand' => $data['quantity_on_hand'] ?? 0,
            'reorder_level' => $data['reorder_level'] ?? 0,
            'unit' => $data['unit'] ?? 'pcs',
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    /** @param array<string, mixed> $data */
    public function updateProduct(Product $product, array $data): Product
    {
        return $this->productRepository->update($product, [
            'category_id' => array_key_exists('category_id', $data) ? $data['category_id'] : $product->category_id,
            'name' => $data['name'] ?? $product->name,
            'sku' => array_key_exists('sku', $data) ? $data['sku'] : $product->sku,
            'reorder_level' => $data['reorder_level'] ?? $product->reorder_level,
            'unit' => $data['unit'] ?? $product->unit,
            'is_active' => $data['is_active'] ?? $product->is_active,
        ]);
    }

    public function deleteProduct(Product $product): bool
    {
        return $this->productRepository->delete($product);
    }

    public function adjustStock(
        Product $product,
        float $quantityDelta,
        string $reason,
        int $adjustedById,
        StockMovementType $type = StockMovementType::Manual,
        ?int $billId = null,
        ?int $purchaseId = null,
    ): Product {
        return DB::transaction(function () use ($product, $quantityDelta, $reason, $adjustedById, $type, $billId, $purchaseId): Product {
            $lockedProduct = $this->productRepository->findForUpdate($product->id) ?? $product;

            $lockedProduct->stockAdjustments()->create([
                'tenant_id' => $lockedProduct->tenant_id,
                'branch_id' => $lockedProduct->branch_id,
                'adjusted_by' => $adjustedById,
                'quantity_delta' => $quantityDelta,
                'reason' => $reason,
                'type' => $type,
                'bill_id' => $billId,
                'purchase_id' => $purchaseId,
            ]);

            $newQuantity = bcadd((string) $lockedProduct->quantity_on_hand, (string) $quantityDelta, 2);

            $this->productRepository->update($lockedProduct, ['quantity_on_hand' => $newQuantity]);

            return $product->refresh();
        });
    }

    /**
     * Removes expired or damaged stock. The quantity is entered as a positive
     * amount and recorded as a deduction.
     */
    public function writeOffStock(Product $product, float $quantity, StockMovementType $type, ?string $reason, int $userId): Product
    {
        if (! in_array($type, [StockMovementType::Expired, StockMovementType::Damaged], true)) {
            throw new InvalidArgumentException('A write-off must be of type expired or damaged.');
        }

        if ($quantity <= 0) {
            throw new InvalidArgumentException('Write-off quantity must be greater than zero.');
        }

        return $this->adjustStock($product, -abs($quantity), $reason ?: $type->label(), $userId, $type);
    }

    /**
     * Resets stock to a physically counted quantity. Bill-time usage is only
     * an estimate, so the counted figure simply overrides the system figure;
     * the difference is recorded as an estimate variance, never as a purchase.
     */
    public function recordCount(Product $product, float $countedQuantity, int $userId): Product
    {
        return DB::transaction(function () use ($product, $countedQuantity, $userId): Product {
            $lockedProduct = $this->productRepository->findForUpdate($product->id) ?? $product;

            $difference = bcsub((string) $countedQuantity, (string) $lockedProduct->quantity_on_hand, 2);

            return $this->adjustStock(
                $lockedProduct,
                (float) $difference,
                'Physical count',
                $userId,
                StockMovementType::CountCorrection,
            );
        });
    }

    /** @param array<string, mixed> $data */
    public function createCategory(array $data): InventoryCategory
    {
        return $this->categoryRepository->create([
            'tenant_id' => $this->tenantContext->get()->id,
            'branch_id' => $this->branchContext->get()->id,
            'name' => $data['name'],
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    /** @param array<string, mixed> $data */
    public function updateCategory(InventoryCategory $category, array $data): InventoryCategory
    {
        return $this->categoryRepository->update($category, [
            'name' => $data['name'] ?? $category->name,
            'is_active' => $data['is_active'] ?? $category->is_active,
        ]);
    }

    public function deleteCategory(InventoryCategory $category): bool
    {
        return $this->categoryRepository->delete($category);
    }
}
