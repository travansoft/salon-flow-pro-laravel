<?php

namespace App\Http\Controllers;

use App\Http\Requests\Inventory\StockCountRequest;
use App\Models\Product;
use App\Services\InventoryService;
use Illuminate\Http\RedirectResponse;

class StockCountsController extends Controller
{
    public function __construct(private InventoryService $inventoryService) {}

    public function store(StockCountRequest $request, string $subdomain, Product $product): RedirectResponse
    {
        abort_unless($request->user()->can('inventory.edit'), 403);

        $this->inventoryService->recordCount(
            $product,
            (float) $request->validated('counted_quantity'),
            $request->user()->id,
        );

        return back()->with('status', "Stock count saved for {$product->name}.");
    }
}
