<?php

namespace App\Http\Controllers;

use App\Http\Requests\Incentive\StaffBonusFilterRequest;
use App\Http\Requests\Incentive\StoreStaffBonusRequest;
use App\Http\Requests\Incentive\UpdateStaffBonusRequest;
use App\Models\StaffIncentive;
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

    public function index(StaffBonusFilterRequest $request): View
    {
        $user = $request->user();

        abort_unless($user->can('incentives.view'), 403);

        $canSeeEveryone = $user->can('incentives.create') || $user->can('incentives.edit');
        $staffProfileId = $request->staffProfileId();

        if (! $canSeeEveryone) {
            abort_unless($user->staffProfile !== null, 403);

            $staffProfileId = $user->staffProfile->id;
        }

        $month = $request->month();
        $bonuses = $this->staffIncentiveRepository->getListBetweenDates(
            $month->copy()->startOfMonth(),
            $month->copy()->endOfMonth(),
            $staffProfileId,
        );

        return view('admin.incentive.bonuses.index', [
            'bonuses' => $bonuses,
            'month' => $month,
            'staff' => $canSeeEveryone ? $this->staffProfileRepository->getActive() : collect(),
            'staffProfileId' => $staffProfileId,
            'total' => $bonuses->reduce(fn (string $carry, StaffIncentive $bonus) => bcadd($carry, (string) $bonus->amount, 2), '0.00'),
        ]);
    }

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

    public function edit(Request $request, string $subdomain, StaffIncentive $staffBonus): View
    {
        abort_unless($request->user()->can('incentives.edit'), 403);

        return view('admin.incentive.bonuses.edit', [
            'bonus' => $staffBonus,
            'staff' => $this->staffProfileRepository->getActive(),
        ]);
    }

    public function update(UpdateStaffBonusRequest $request, string $subdomain, StaffIncentive $staffBonus): RedirectResponse
    {
        abort_unless($request->user()->can('incentives.edit'), 403);

        $this->staffIncentiveRepository->update($staffBonus, $request->validated());

        return redirect($this->tenantUrl->route('staffBonuses.index')."?month={$staffBonus->awarded_date->format('Y-m')}")
            ->with('status', 'Bonus updated.');
    }

    public function destroy(Request $request, string $subdomain, StaffIncentive $staffBonus): RedirectResponse
    {
        abort_unless($request->user()->can('incentives.delete'), 403);

        $this->staffIncentiveRepository->delete($staffBonus);

        return redirect($this->tenantUrl->route('staffBonuses.index')."?month={$staffBonus->awarded_date->format('Y-m')}")
            ->with('status', 'Bonus deleted.');
    }
}
