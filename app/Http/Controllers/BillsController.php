<?php

namespace App\Http\Controllers;

use App\Http\Requests\Billing\GenerateBillFromAppointmentRequest;
use App\Http\Requests\Billing\RecordPaymentRequest;
use App\Http\Requests\Billing\RefundBillRequest;
use App\Http\Requests\Billing\SettleQuickBillRequest;
use App\Models\Appointment;
use App\Models\Bill;
use App\Repositories\Contracts\BillRepositoryInterface;
use App\Services\BillingService;
use App\Services\QuickBillService;
use App\Services\TenantUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class BillsController extends Controller
{
    public function __construct(
        private BillRepositoryInterface $billRepository,
        private BillingService $billingService,
        private QuickBillService $quickBillService,
        private TenantUrl $tenantUrl,
    ) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('billing.view'), 403);

        $fromDate = $request->query('from_date', now()->toDateString());
        $toDate = $request->query('to_date', now()->toDateString());
        $clientName = $request->query('client_name');
        $clientPhone = $request->query('client_phone');

        $bills = $this->billRepository->search($fromDate, $toDate, $clientName, $clientPhone);

        return view('admin.bills.index', [
            'bills' => $bills,
            'fromDate' => $fromDate,
            'toDate' => $toDate,
            'clientName' => $clientName,
            'clientPhone' => $clientPhone,
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->can('billing.create'), 403);

        return view('admin.bills.create');
    }

    public function generateFromAppointment(GenerateBillFromAppointmentRequest $request, string $subdomain, Appointment $appointment): RedirectResponse
    {
        abort_unless($request->user()->can('billing.create'), 403);

        $bill = $this->billingService->generateFromAppointment(
            $appointment,
            $request->user()->id,
            $request->validated()['manual_items'] ?? [],
        );

        return redirect($this->tenantUrl->route('bills.show', ['bill' => $bill]))->with('status', 'Bill generated.');
    }

    public function settle(SettleQuickBillRequest $request): JsonResponse
    {
        abort_unless($request->user()->can('billing.create'), 403);

        $data = $request->validated();

        try {
            $bill = $this->quickBillService->createAndSettle(
                $data['items'],
                [
                    'client_id' => $data['client_id'] ?? null,
                    'name' => $data['client_name'] ?? null,
                    'phone' => $data['client_phone'] ?? null,
                    'gst_number' => $data['client_gst_number'] ?? null,
                ],
                $data['payment_method'],
                $request->user()->id,
                (float) ($data['discount_percent'] ?? 0),
            );
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'bill_id' => $bill->id,
            'bill_number' => $bill->bill_number,
            'total' => (float) $bill->total,
            'redirect' => $this->tenantUrl->route('bills.show', ['bill' => $bill]),
        ]);
    }

    public function show(Request $request, string $subdomain, Bill $bill): View
    {
        abort_unless($request->user()->can('billing.view'), 403);

        $bill->load(['lineItems.staffProfile', 'payments', 'refunds', 'client', 'createdBy', 'branch']);

        return view('admin.bills.show', ['bill' => $bill]);
    }

    public function print(Request $request, string $subdomain, Bill $bill): View
    {
        abort_unless($request->user()->can('billing.view'), 403);

        $bill->load(['lineItems.service', 'client', 'tenant', 'createdBy', 'branch']);

        return view('admin.bills.print', ['bill' => $bill]);
    }

    public function recordPayment(RecordPaymentRequest $request, string $subdomain, Bill $bill): RedirectResponse
    {
        abort_unless($request->user()->can('billing.create'), 403);

        $this->billingService->recordPayments($bill, $request->validated()['payments'], $request->user()->id);

        return redirect($this->tenantUrl->route('bills.show', ['bill' => $bill]))->with('status', 'Payment recorded.');
    }

    public function refund(RefundBillRequest $request, string $subdomain, Bill $bill): RedirectResponse
    {
        abort_unless($request->user()->can('billing.edit'), 403);

        $data = $request->validated();

        try {
            $this->billingService->refund($bill, $data['amount'], $data['reason'], $request->user()->id);
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['amount' => $exception->getMessage()]);
        }

        return redirect($this->tenantUrl->route('bills.show', ['bill' => $bill]))->with('status', 'Refund recorded.');
    }
}
