<?php

namespace App\Http\Controllers;

use App\Http\Requests\BridalEngagements\AttachBridalEngagementBillRequest;
use App\Http\Requests\BridalEngagements\CreateBridalEngagementBillRequest;
use App\Models\Bill;
use App\Models\BridalEngagement;
use App\Repositories\Contracts\BillRepositoryInterface;
use App\Services\BridalEngagementService;
use App\Services\TenantUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class BridalEngagementBillsController extends Controller
{
    public function __construct(
        private BridalEngagementService $bridalEngagementService,
        private BillRepositoryInterface $billRepository,
        private TenantUrl $tenantUrl,
    ) {}

    public function lookup(Request $request, string $subdomain, BridalEngagement $bridalEngagement): JsonResponse
    {
        abort_unless($request->user()->can('billing.create'), 403);

        $bill = $this->bridalEngagementService->findBillByNumber((string) $request->query('number', ''));

        if ($bill === null) {
            return response()->json(['message' => 'No bill found with that number.'], 404);
        }

        $bill->load(['client', 'branch']);

        $unavailableReason = match (true) {
            $bill->status === Bill::StatusVoid => 'This bill is void and cannot be attached.',
            $bill->bridal_engagement_id === $bridalEngagement->id => 'This bill is already attached to this event.',
            $bill->bridal_engagement_id !== null => 'This bill is already attached to another event.',
            default => null,
        };

        return response()->json([
            'bill' => [
                'id' => $bill->id,
                'invoice_number' => $bill->invoiceNumber(),
                'client' => $bill->client?->name,
                'date' => $bill->created_at->format('d M Y'),
                'total' => number_format((float) $bill->total, 2),
                'paid' => number_format((float) $bill->amount_paid, 2),
                'status' => ucfirst($bill->status),
                'unavailable_reason' => $unavailableReason,
            ],
        ]);
    }

    public function store(CreateBridalEngagementBillRequest $request, string $subdomain, BridalEngagement $bridalEngagement): RedirectResponse
    {
        abort_unless($request->user()->can('billing.create'), 403);

        $data = $request->validated();

        try {
            $bill = $this->bridalEngagementService->createBill(
                $bridalEngagement,
                $request->user()->id,
                Carbon::parse($data['bill_date'])->startOfDay(),
                (float) $data['amount'],
                $data['payment_method'],
                $data['staff'],
            );
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['bill' => $exception->getMessage()])->withInput();
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
