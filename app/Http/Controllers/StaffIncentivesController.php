<?php

namespace App\Http\Controllers;

use App\Http\Requests\Commission\StoreStaffIncentiveRequest;
use App\Repositories\Contracts\StaffProfileRepositoryInterface;
use App\Services\BranchContext;
use App\Services\CommissionService;
use App\Services\TenantContext;
use App\Services\TenantUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StaffIncentivesController extends Controller
{
    public function __construct(
        private StaffProfileRepositoryInterface $staffProfileRepository,
        private CommissionService $commissionService,
        private TenantContext $tenantContext,
        private BranchContext $branchContext,
        private TenantUrl $tenantUrl,
    ) {}

    public function create(Request $request): View
    {
        abort_unless($request->user()->can('commissions.create'), 403);

        return view('admin.commission.incentives.create', [
            'staff' => $this->staffProfileRepository->getActive(),
        ]);
    }

    public function store(StoreStaffIncentiveRequest $request): RedirectResponse
    {
        abort_unless($request->user()->can('commissions.create'), 403);

        $this->commissionService->awardIncentive([
            ...$request->validated(),
            'tenant_id' => $this->tenantContext->get()->id,
            'branch_id' => $this->branchContext->get()->id,
            'awarded_by' => $request->user()->id,
        ]);

        return redirect($this->tenantUrl->route('commissionEarnings.index'))->with('status', 'Incentive awarded.');
    }
}
