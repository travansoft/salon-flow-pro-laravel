<?php

namespace App\Http\Controllers;

use App\Enums\StockMovementType;
use App\Http\Requests\Inventory\AdjustStockRequest;
use App\Models\Product;
use App\Services\InventoryService;
use App\Services\TenantUrl;
use Illuminate\Http\RedirectResponse;

class StockAdjustmentsController extends Controller
{
    public function __construct(
        private InventoryService $inventoryService,
        private TenantUrl $tenantUrl,
    ) {}

    public function store(AdjustStockRequest $request, string $subdomain, Product $product): RedirectResponse
    {
        abort_unless($request->user()->can('inventory.edit'), 403);

        $type = StockMovementType::tryFrom((string) $request->validated('type')) ?? StockMovementType::Manual;

        if ($type === StockMovementType::Manual) {
            $this->inventoryService->adjustStock(
                $product,
                (float) $request->validated('quantity_delta'),
                $request->validated('reason'),
                $request->user()->id,
            );

            return redirect($this->tenantUrl->route('products.show', ['product' => $product]))->with('status', 'Stock adjusted.');
        }

        $this->inventoryService->writeOffStock(
            $product,
            (float) $request->validated('quantity_delta'),
            $type,
            $request->validated('reason'),
            $request->user()->id,
        );

        return redirect($this->tenantUrl->route('products.show', ['product' => $product]))->with('status', 'Stock written off.');
    }
}
