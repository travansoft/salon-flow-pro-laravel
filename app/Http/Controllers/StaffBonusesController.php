<?php

namespace App\Http\Controllers;

use App\Http\Requests\Incentive\StoreStaffBonusRequest;
use App\Repositories\Contracts\StaffIncentiveRepositoryInterface;
use App\Repositories\Contracts\StaffProfileRepositoryInterface;
use App\Services\BranchContext;
use App\Services\TenantContext;
use App\Services\TenantUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StaffBonusesController extends Controller
{
    public function __construct(
        private StaffProfileRepositoryInterface $staffProfileRepository,
        private StaffIncentiveRepositoryInterface $staffIncentiveRepository,
        private TenantContext $tenantContext,
        private BranchContext $branchContext,
        private TenantUrl $tenantUrl,
    ) {}

    public function create(Request $request): View
    {
        abort_unless($request->user()->can('incentives.create'), 403);

        return view('admin.incentive.bonuses.create', [
            'staff' => $this->staffProfileRepository->getActive(),
        ]);
    }

    public function store(StoreStaffBonusRequest $request): RedirectResponse
    {
        abort_unless($request->user()->can('incentives.create'), 403);

        $this->staffIncentiveRepository->create([
            ...$request->validated(),
            'tenant_id' => $this->tenantContext->get()->id,
            'branch_id' => $this->branchContext->get()->id,
            'awarded_by' => $request->user()->id,
        ]);

        return redirect($this->tenantUrl->route('incentiveProgress.index'))->with('status', 'Bonus awarded.');
    }
}
