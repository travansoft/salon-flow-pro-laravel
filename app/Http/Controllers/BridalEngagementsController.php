<?php

namespace App\Http\Controllers;

use App\Http\Requests\BridalEngagements\StoreBridalEngagementRequest;
use App\Http\Requests\BridalEngagements\UpdateBridalEngagementRequest;
use App\Models\BridalEngagement;
use App\Repositories\Contracts\BillRepositoryInterface;
use App\Repositories\Contracts\BridalEngagementRepositoryInterface;
use App\Services\BridalEngagementService;
use App\Services\TenantUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BridalEngagementsController extends Controller
{
    public function __construct(
        private BridalEngagementRepositoryInterface $bridalEngagementRepository,
        private BillRepositoryInterface $billRepository,
        private BridalEngagementService $bridalEngagementService,
        private TenantUrl $tenantUrl,
    ) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('appointments.view'), 403);

        $engagements = $this->bridalEngagementRepository->getUpcoming();

        return view('admin.bridal-engagements.index', ['engagements' => $engagements]);
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->can('appointments.create'), 403);

        return view('admin.bridal-engagements.create');
    }

    public function store(StoreBridalEngagementRequest $request): RedirectResponse
    {
        abort_unless($request->user()->can('appointments.create'), 403);

        $engagement = $this->bridalEngagementService->createEngagement($request->validated());

        return redirect($this->tenantUrl->route('bridalEngagements.show', ['bridalEngagement' => $engagement]))->with('status', 'Bridal engagement created.');
    }

    public function show(Request $request, string $subdomain, BridalEngagement $bridalEngagement): View
    {
        abort_unless($request->user()->can('appointments.view'), 403);

        $bridalEngagement->load(['client', 'bills.client']);

        return view('admin.bridal-engagements.show', [
            'engagement' => $bridalEngagement,
            'summary' => $this->bridalEngagementService->summarize($bridalEngagement),
            'attachableBills' => $request->user()->can('billing.create')
                ? $this->billRepository->getAttachableToEngagement()
                : collect(),
        ]);
    }

    public function edit(Request $request, string $subdomain, BridalEngagement $bridalEngagement): View
    {
        abort_unless($request->user()->can('appointments.edit'), 403);

        $bridalEngagement->load('client');

        return view('admin.bridal-engagements.edit', ['engagement' => $bridalEngagement]);
    }

    public function update(UpdateBridalEngagementRequest $request, string $subdomain, BridalEngagement $bridalEngagement): RedirectResponse
    {
        abort_unless($request->user()->can('appointments.edit'), 403);

        $this->bridalEngagementService->updateEngagement($bridalEngagement, $request->validated());

        return redirect($this->tenantUrl->route('bridalEngagements.show', ['bridalEngagement' => $bridalEngagement]))->with('status', 'Bridal engagement updated.');
    }

    public function destroy(Request $request, string $subdomain, BridalEngagement $bridalEngagement): RedirectResponse
    {
        abort_unless($request->user()->can('appointments.delete'), 403);

        $this->bridalEngagementService->deleteEngagement($bridalEngagement);

        return redirect($this->tenantUrl->route('bridalEngagements.index'))->with('status', 'Bridal engagement deleted.');
    }
}
