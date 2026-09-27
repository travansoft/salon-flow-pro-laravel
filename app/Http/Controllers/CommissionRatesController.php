<?php

namespace App\Http\Controllers;

use App\Http\Requests\Commission\StoreCommissionRateRequest;
use App\Http\Requests\Commission\UpdateCommissionRateRequest;
use App\Models\CommissionRate;
use App\Repositories\Contracts\CommissionRateRepositoryInterface;
use App\Repositories\Contracts\ServiceCategoryRepositoryInterface;
use App\Repositories\Contracts\StaffProfileRepositoryInterface;
use App\Services\BranchContext;
use App\Services\CommissionService;
use App\Services\TenantContext;
use App\Services\TenantUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CommissionRatesController extends Controller
{
    public function __construct(
        private CommissionRateRepositoryInterface $commissionRateRepository,
        private StaffProfileRepositoryInterface $staffProfileRepository,
        private ServiceCategoryRepositoryInterface $categoryRepository,
        private CommissionService $commissionService,
        private TenantContext $tenantContext,
        private BranchContext $branchContext,
        private TenantUrl $tenantUrl,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user->can('commissions.create') || $user->can('commissions.edit') || $user->can('commissions.delete'), 403);

        $rates = $this->commissionRateRepository->getAll();

        return view('admin.commission.rates.index', ['rates' => $rates]);
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->can('commissions.create'), 403);

        return view('admin.commission.rates.create', [
            'staff' => $this->staffProfileRepository->getActive(),
            'categories' => $this->categoryRepository->getActive(),
        ]);
    }

    public function store(StoreCommissionRateRequest $request): RedirectResponse
    {
        abort_unless($request->user()->can('commissions.create'), 403);

        $this->commissionService->setRate([
            ...$request->validated(),
            'tenant_id' => $this->tenantContext->get()->id,
            'branch_id' => $this->branchContext->get()->id,
        ]);

        return redirect($this->tenantUrl->route('commissionRates.index'))->with('status', 'Commission rate created.');
    }

    public function edit(Request $request, string $subdomain, CommissionRate $commissionRate): View
    {
        abort_unless($request->user()->can('commissions.edit'), 403);

        return view('admin.commission.rates.edit', [
            'rate' => $commissionRate,
            'staff' => $this->staffProfileRepository->getActive(),
            'categories' => $this->categoryRepository->getActive(),
        ]);
    }

    public function update(UpdateCommissionRateRequest $request, string $subdomain, CommissionRate $commissionRate): RedirectResponse
    {
        abort_unless($request->user()->can('commissions.edit'), 403);

        $this->commissionService->updateRate($commissionRate, $request->validated());

        return redirect($this->tenantUrl->route('commissionRates.index'))->with('status', 'Commission rate updated.');
    }

    public function destroy(Request $request, string $subdomain, CommissionRate $commissionRate): RedirectResponse
    {
        abort_unless($request->user()->can('commissions.delete'), 403);

        $this->commissionService->deleteRate($commissionRate);

        return redirect($this->tenantUrl->route('commissionRates.index'))->with('status', 'Commission rate removed.');
    }
}
