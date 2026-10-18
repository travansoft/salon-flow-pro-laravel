<?php

namespace App\Http\Controllers;

use App\Http\Requests\Dashboard\DashboardRangeRequest;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Services\BillDraftService;
use App\Services\DashboardService;
use App\Services\ReportService;
use App\Services\SalesInsightsService;
use Illuminate\View\View;

class TenantDashboardController extends Controller
{
    public function __construct(
        private DashboardService $dashboardService,
        private ProductRepositoryInterface $productRepository,
        private BillDraftService $billDraftService,
        private ReportService $reportService,
        private SalesInsightsService $salesInsightsService,
    ) {}

    public function index(DashboardRangeRequest $request): View
    {
        [$from, $to] = $request->range();
        $insights = $this->salesInsightsService->forRange($from, $to);

        return view('admin.dashboard', [
            'period' => $request->period(),
            'periodLabel' => $request->periodLabel(),
            'periodTitle' => $request->periodTitle(),
            'from' => $from,
            'to' => $to,
            ...$this->dashboardService->summaryFor($from, $to),
            'revenuePoints' => array_map(fn (array $day): array => [
                'label' => $day['label'],
                'value' => $day['amount'],
                'display' => $day['short'],
                'title' => '₹'.number_format((float) $day['amount'], 2),
            ], $insights['dailyRevenue']),
            'footfallPoints' => array_map(fn (array $day): array => [
                'label' => $day['label'],
                'value' => $day['footfall'],
                'display' => (string) $day['footfall'],
            ], $insights['dailyRevenue']),
            'trendTruncated' => $insights['trendTruncated'],
            'revenueChange' => $insights['revenueChange'],
            'paymentMix' => $this->reportService->reportFor($from, $to)['paymentMix'],
            'lowStockCount' => $this->productRepository->getLowStock()->count(),
            'drafts' => $request->user()->can('billing.create')
                ? $this->billDraftService->listForUser($request->user())
                : collect(),
        ]);
    }
}
