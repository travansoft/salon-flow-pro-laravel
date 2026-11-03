<?php

namespace App\Http\Controllers;

use App\Http\Requests\Billing\CorrectBillGstRateRequest;
use App\Services\BillGstRateCorrectionService;
use App\Services\TenantContext;
use App\Services\TenantUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class BillGstCorrectionsController extends Controller
{
    public function __construct(
        private BillGstRateCorrectionService $correctionService,
        private TenantContext $tenantContext,
        private TenantUrl $tenantUrl,
    ) {}

    public function create(Request $request): View
    {
        abort_unless($request->user()->can('billing.correctGst'), 403);

        return view('admin.bills.gstCorrection', [
            'result' => null,
            'input' => ['to_rate' => $this->tenantContext->get()->default_gst_rate],
        ]);
    }

    public function store(CorrectBillGstRateRequest $request): View|RedirectResponse
    {
        abort_unless($request->user()->can('billing.correctGst'), 403);

        $data = $request->validated();
        $apply = $request->boolean('confirm');

        $result = $this->correctionService->correct(
            (string) $data['from_rate'],
            (string) $data['to_rate'],
            Carbon::parse($data['from_date']),
            Carbon::parse($data['to_date']),
            $apply,
            $request->user()->id,
        );

        if (! $apply) {
            return view('admin.bills.gstCorrection', ['result' => $result, 'input' => $data]);
        }

        return redirect($this->tenantUrl->route('billGstCorrections.create'))
            ->with('status', "Corrected GST on {$result['bills']} bill(s).");
    }
}
