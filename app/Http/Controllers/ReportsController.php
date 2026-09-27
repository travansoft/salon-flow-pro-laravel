<?php

namespace App\Http\Controllers;

use App\Services\BranchContext;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class ReportsController extends Controller
{
    public function __construct(
        private ReportService $reportService,
        private BranchContext $branchContext,
    ) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('dashboard.view'), 403);

        $period = $request->query('period', 'month');
        [$from, $to] = $this->rangeFor($period);

        return view('admin.reports.index', [
            'period' => $period,
            ...$this->reportService->reportFor($from, $to),
        ]);
    }

    public function consolidated(Request $request): View
    {
        abort_unless($request->user()->can('reports.consolidated.view'), 403);

        $period = $request->query('period', 'month');
        [$from, $to] = $this->rangeFor($period);

        $this->branchContext->bypass();

        return view('admin.reports.consolidated', [
            'period' => $period,
            ...$this->reportService->reportFor($from, $to),
        ]);
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function rangeFor(string $period): array
    {
        $today = Carbon::today();

        return match ($period) {
            'today' => [$today->copy(), $today->copy()],
            'week' => [$today->copy()->startOfWeek(), $today->copy()],
            default => [$today->copy()->startOfMonth(), $today->copy()],
        };
    }
}
