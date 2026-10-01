<?php

namespace App\Http\Controllers;

use App\Http\Requests\Incentive\StoreIncentiveSlabRequest;
use App\Http\Requests\Incentive\UpdateIncentiveSettingsRequest;
use App\Http\Requests\Incentive\UpdateIncentiveSlabRequest;
use App\Models\IncentiveSlab;
use App\Services\IncentiveService;
use App\Services\TenantContext;
use App\Services\TenantUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class IncentiveSettingsController extends Controller
{
    public function __construct(
        private IncentiveService $incentiveService,
        private TenantContext $tenantContext,
        private TenantUrl $tenantUrl,
    ) {}

    public function edit(Request $request): View
    {
        abort_unless($request->user()->can('incentives.edit'), 403);

        return view('admin.incentive.settings.edit', [
            'settings' => $this->incentiveService->getSettings(),
            'slabs' => $this->incentiveService->getSlabs(),
        ]);
    }

    public function update(UpdateIncentiveSettingsRequest $request): RedirectResponse
    {
        abort_unless($request->user()->can('incentives.edit'), 403);

        $this->incentiveService->updateSettings($request->validated());

        return redirect($this->tenantUrl->route('incentiveSettings.edit'))->with('status', 'Referral split updated.');
    }

    public function storeSlab(StoreIncentiveSlabRequest $request): RedirectResponse
    {
        abort_unless($request->user()->can('incentives.create'), 403);

        $this->incentiveService->createSlab([
            ...$request->validated(),
            'tenant_id' => $this->tenantContext->get()->id,
        ]);

        return redirect($this->tenantUrl->route('incentiveSettings.edit'))->with('status', 'Slab added.');
    }

    public function updateSlab(UpdateIncentiveSlabRequest $request, string $subdomain, IncentiveSlab $incentiveSlab): RedirectResponse
    {
        abort_unless($request->user()->can('incentives.edit'), 403);

        $this->incentiveService->updateSlab($incentiveSlab, $request->validated());

        return redirect($this->tenantUrl->route('incentiveSettings.edit'))->with('status', 'Slab updated.');
    }

    public function destroySlab(Request $request, string $subdomain, IncentiveSlab $incentiveSlab): RedirectResponse
    {
        abort_unless($request->user()->can('incentives.delete'), 403);

        $this->incentiveService->deleteSlab($incentiveSlab);

        return redirect($this->tenantUrl->route('incentiveSettings.edit'))->with('status', 'Slab removed.');
    }
}
