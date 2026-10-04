<?php

namespace App\Http\Controllers;

use App\Http\Requests\BridalEngagements\AttachBridalEngagementBillRequest;
use App\Models\Bill;
use App\Models\BridalEngagement;
use App\Repositories\Contracts\BillRepositoryInterface;
use App\Services\BridalEngagementService;
use App\Services\TenantUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class BridalEngagementBillsController extends Controller
{
    public function __construct(
        private BridalEngagementService $bridalEngagementService,
        private BillRepositoryInterface $billRepository,
        private TenantUrl $tenantUrl,
    ) {}

    public function store(Request $request, string $subdomain, BridalEngagement $bridalEngagement): RedirectResponse
    {
        abort_unless($request->user()->can('billing.create'), 403);

        try {
            $bill = $this->bridalEngagementService->createBill($bridalEngagement, $request->user()->id);
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['bill' => $exception->getMessage()]);
        }

        return redirect($this->tenantUrl->route('bills.show', ['bill' => $bill]))->with('status', 'Bill created for event.');
    }

    public function attach(AttachBridalEngagementBillRequest $request, string $subdomain, BridalEngagement $bridalEngagement): RedirectResponse
    {
        abort_unless($request->user()->can('billing.create'), 403);

        $bill = $this->billRepository->findById($request->validated()['bill_id']);

        abort_if($bill === null, 404);

        try {
            $this->bridalEngagementService->attachBill($bridalEngagement, $bill);
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['bill_id' => $exception->getMessage()]);
        }

        return redirect($this->tenantUrl->route('bridalEngagements.show', ['bridalEngagement' => $bridalEngagement]))->with('status', 'Bill attached.');
    }

    public function detach(Request $request, string $subdomain, BridalEngagement $bridalEngagement, Bill $bill): RedirectResponse
    {
        abort_unless($request->user()->can('billing.create'), 403);

        try {
            $this->bridalEngagementService->detachBill($bridalEngagement, $bill);
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['bill' => $exception->getMessage()]);
        }

        return redirect($this->tenantUrl->route('bridalEngagements.show', ['bridalEngagement' => $bridalEngagement]))->with('status', 'Bill detached.');
    }
}
