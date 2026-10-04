<?php

namespace App\Http\Controllers;

use App\Http\Requests\Billing\SaveBillDraftRequest;
use App\Services\BillDraftService;
use App\Services\TenantUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BillDraftsController extends Controller
{
    public function __construct(
        private BillDraftService $billDraftService,
        private TenantUrl $tenantUrl,
    ) {}

    public function store(SaveBillDraftRequest $request): JsonResponse
    {
        abort_unless($request->user()->can('billing.create'), 403);

        $draft = $this->billDraftService->save($request->user(), $request->validated());

        return response()->json([
            'draft_id' => $draft->id,
            'redirect' => $this->tenantUrl->route('bills.index'),
        ]);
    }

    public function update(SaveBillDraftRequest $request, string $subdomain, int $billDraft): JsonResponse
    {
        abort_unless($request->user()->can('billing.create'), 403);

        $draft = $this->billDraftService->save($request->user(), $request->validated(), $billDraft);

        abort_if($draft === null, 404);

        return response()->json([
            'draft_id' => $draft->id,
            'redirect' => $this->tenantUrl->route('bills.index'),
        ]);
    }

    public function destroy(Request $request, string $subdomain, int $billDraft): RedirectResponse
    {
        abort_unless($request->user()->can('billing.create'), 403);

        abort_unless($this->billDraftService->discard($request->user(), $billDraft), 404);

        return redirect($this->tenantUrl->route('bills.index'))->with('status', 'Draft discarded.');
    }
}
