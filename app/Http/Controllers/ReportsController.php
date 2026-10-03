<?php

namespace App\Http\Controllers;

use App\Http\Requests\Expenses\ExpenseFilterRequest;
use App\Http\Requests\Incentive\IncentiveMonthRequest;
use App\Http\Requests\Reports\GstReportRequest;
use App\Services\BranchContext;
use App\Services\DayBookService;
use App\Services\ExpenseSummaryService;
use App\Services\GstReportService;
use App\Services\ReportService;
use App\Services\SalesInsightsService;
use App\Services\TargetTrackerService;
use App\Services\XlsxWriter;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportsController extends Controller
{
    public function __construct(
        private ReportService $reportService,
        private BranchContext $branchContext,
        private DayBookService $dayBookService,
        private SalesInsightsService $salesInsightsService,
        private GstReportService $gstReportService,
        private TargetTrackerService $targetTrackerService,
        private ExpenseSummaryService $expenseSummaryService,
    ) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('dashboard.view'), 403);

        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        if (isset($validated['from'])) {
            $from = Carbon::parse($validated['from'])->startOfDay();
            $to = Carbon::parse($validated['to'] ?? $from)->startOfDay();
        } else {
            [$from, $to] = $this->rangeFor($request->query('period', 'month'));
        }

        return view('admin.reports.index', [
            'from' => $from,
            'to' => $to,
            ...$this->reportService->reportFor($from, $to),
            ...$this->salesInsightsService->forRange($from, $to),
        ]);
    }

    public function dayBook(Request $request): View
    {
        abort_unless($request->user()->can('dashboard.view'), 403);

        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $from = Carbon::parse($validated['from'] ?? 'today')->startOfDay();
        $to = Carbon::parse($validated['to'] ?? $from)->startOfDay();

        return view('admin.reports.dayBook', [
            'from' => $from,
            'to' => $to,
            ...$this->dayBookService->forRange($from, $to),
        ]);
    }

    public function expenseSummary(ExpenseFilterRequest $request): View
    {
        abort_unless($request->user()->can('dashboard.view'), 403);

        [$from, $to] = $request->range();

        return view('admin.reports.expenseSummary', [
            'from' => $from,
            'to' => $to,
            ...$this->expenseSummaryService->forRange($from, $to),
        ]);
    }

    public function gst(GstReportRequest $request): View
    {
        abort_unless($request->user()->can('dashboard.view'), 403);

        $month = $request->month();

        return view('admin.reports.gst', [
            'month' => $month,
            ...$this->gstReportService->forMonth($month),
        ]);
    }

    public function gstExport(GstReportRequest $request, XlsxWriter $xlsxWriter): BinaryFileResponse
    {
        abort_unless($request->user()->can('dashboard.view'), 403);

        $month = $request->month();
        $report = $this->gstReportService->forMonth($month);

        $path = $xlsxWriter->build(
            'GST report',
            GstReportService::Headings,
            $this->gstReportService->exportRows($report['invoices']),
        );

        return response()
            ->download($path, "gst-report-{$month->format('Y-m')}.xlsx")
            ->deleteFileAfterSend();
    }

    public function targetTracker(IncentiveMonthRequest $request): View
    {
        abort_unless($request->user()->can('dashboard.view'), 403);

        $month = $request->month();
        $tracker = $this->targetTrackerService->forMonth($month);
        $selectedStaff = $tracker['staff']->get((int) $request->query('staff'));

        return view('admin.reports.targetTracker', [
            'month' => $month,
            'monthQuery' => ['month' => $month->format('Y-m')],
            'statusStyles' => TargetTrackerService::StatusStyles,
            'salon' => $tracker['salon'],
            'staffRows' => $tracker['staff'],
            'selectedStaff' => $selectedStaff,
            'detail' => $selectedStaff ?? $tracker['salon'],
        ]);
    }

    public function targetTrackerExport(IncentiveMonthRequest $request, XlsxWriter $xlsxWriter): BinaryFileResponse
    {
        abort_unless($request->user()->can('dashboard.view'), 403);

        $month = $request->month();
        $tracker = $this->targetTrackerService->forMonth($month);

        $path = $xlsxWriter->build(
            'Target tracker',
            TargetTrackerService::Headings,
            $this->targetTrackerService->exportRows($tracker['salon'], $tracker['staff']),
        );

        return response()
            ->download($path, "target-tracker-{$month->format('Y-m')}.xlsx")
            ->deleteFileAfterSend();
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
