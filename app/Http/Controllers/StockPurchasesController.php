<?php

namespace App\Http\Controllers;

use App\Http\Requests\Inventory\StorePurchaseRequest;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Repositories\Contracts\StockPurchaseRepositoryInterface;
use App\Services\StockPurchaseService;
use App\Services\TenantUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StockPurchasesController extends Controller
{
    public function __construct(
        private StockPurchaseRepositoryInterface $purchaseRepository,
        private ProductRepositoryInterface $productRepository,
        private StockPurchaseService $purchaseService,
        private TenantUrl $tenantUrl,
    ) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('inventory.view'), 403);

        return view('admin.stock-purchases.index', ['purchases' => $this->purchaseRepository->getRecent()]);
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->can('inventory.create'), 403);

        return view('admin.stock-purchases.create', [
            'products' => $this->productRepository->getActive(),
            'selectedProductId' => $request->integer('product') ?: null,
        ]);
    }

    public function store(StorePurchaseRequest $request): RedirectResponse
    {
        abort_unless($request->user()->can('inventory.create'), 403);

        $product = $this->productRepository->findById((int) $request->validated('product_id'));

        abort_unless($product, 404);

        $this->purchaseService->recordPurchase($product, $request->validated(), $request->user()->id);

        return redirect($this->tenantUrl->route('stockPurchases.index'))->with('status', 'Purchase recorded and stock updated.');
    }
}
