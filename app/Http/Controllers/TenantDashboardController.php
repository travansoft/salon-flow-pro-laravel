<?php

namespace App\Http\Controllers;

use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Services\BillDraftService;
use App\Services\DashboardService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class TenantDashboardController extends Controller
{
    public function __construct(
        private DashboardService $dashboardService,
        private ProductRepositoryInterface $productRepository,
        private BillDraftService $billDraftService,
    ) {}

    public function index(Request $request): View
    {
        return view('admin.dashboard', [
            ...$this->dashboardService->summaryFor(Carbon::today()),
            'lowStockCount' => $this->productRepository->getLowStock()->count(),
            'drafts' => $request->user()->can('billing.create')
                ? $this->billDraftService->listForUser($request->user())
                : collect(),
        ]);
    }
}
