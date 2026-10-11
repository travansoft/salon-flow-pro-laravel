<?php

namespace App\Http\Controllers;

use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Repositories\Contracts\StockAdjustmentRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LowStockReportController extends Controller
{
    public function __construct(
        private ProductRepositoryInterface $productRepository,
        private StockAdjustmentRepositoryInterface $adjustmentRepository,
    ) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('inventory.view'), 403);

        $categoryId = $request->integer('category') ?: null;

        $products = $this->productRepository->getLowStock();

        if ($categoryId) {
            $products = $products->where('category_id', $categoryId)->values();
        }

        return view('admin.inventory-reports.low-stock', [
            'products' => $products,
            'countStats' => $this->adjustmentRepository->getCountStats($products->pluck('id')->all()),
            'categories' => $products->pluck('category')->filter()->unique('id')->values(),
            'categoryId' => $categoryId,
        ]);
    }
}
