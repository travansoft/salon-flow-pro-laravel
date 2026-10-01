<?php

namespace App\Http\Controllers;

use App\Http\Requests\Incentive\CopyStaffTargetsRequest;
use App\Http\Requests\Incentive\IncentiveMonthRequest;
use App\Http\Requests\Incentive\SetStaffTargetsRequest;
use App\Repositories\Contracts\StaffProfileRepositoryInterface;
use App\Services\IncentiveService;
use App\Services\TenantUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class StaffTargetsController extends Controller
{
    public function __construct(
        private StaffProfileRepositoryInterface $staffProfileRepository,
        private IncentiveService $incentiveService,
        private TenantUrl $tenantUrl,
    ) {}

    public function index(IncentiveMonthRequest $request): View
    {
        abort_unless($request->user()->can('incentives.edit'), 403);

        $month = $request->month();

        return view('admin.incentive.targets.index', [
            'month' => $month,
            'staff' => $this->staffProfileRepository->getActive(),
            'targets' => $this->incentiveService->targetsForMonth($month),
        ]);
    }

    public function store(SetStaffTargetsRequest $request): RedirectResponse
    {
        abort_unless($request->user()->can('incentives.edit'), 403);

        $month = $request->month();

        $this->incentiveService->setTargets($month, $request->targets());

        return $this->redirectToMonth($month)->with('status', 'Targets saved.');
    }

    public function copy(CopyStaffTargetsRequest $request): RedirectResponse
    {
        abort_unless($request->user()->can('incentives.edit'), 403);

        $copied = $this->incentiveService->copyTargets($request->fromMonth(), $request->toMonth());

        return $this->redirectToMonth($request->toMonth())->with('status', "Copied {$copied} targets.");
    }

    private function redirectToMonth(Carbon $month): RedirectResponse
    {
        return redirect($this->tenantUrl->route('staffTargets.index').'?month='.$month->format('Y-m'));
    }
}
