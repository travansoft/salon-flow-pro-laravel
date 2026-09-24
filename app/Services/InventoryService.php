<?php

namespace App\Services;

use App\Models\InventoryCategory;
use App\Models\Product;
use App\Repositories\Contracts\InventoryCategoryRepositoryInterface;
use App\Repositories\Contracts\ProductRepositoryInterface;
use Illuminate\Support\Facades\DB;

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

    public function adjustStock(Product $product, float $quantityDelta, string $reason, int $adjustedById): Product
    {
        return DB::transaction(function () use ($product, $quantityDelta, $reason, $adjustedById): Product {
            $product->stockAdjustments()->create([
                'tenant_id' => $product->tenant_id,
                'branch_id' => $product->branch_id,
                'adjusted_by' => $adjustedById,
                'quantity_delta' => $quantityDelta,
                'reason' => $reason,
            ]);

            $newQuantity = bcadd((string) $product->quantity_on_hand, (string) $quantityDelta, 2);

            $this->productRepository->update($product, ['quantity_on_hand' => $newQuantity]);

            return $product->refresh();
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
