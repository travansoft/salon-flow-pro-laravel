<?php

namespace App\Services;

use App\Models\Bill;
use App\Models\BridalEngagement;
use App\Models\Client;
use App\Repositories\Contracts\BillRepositoryInterface;
use App\Repositories\Contracts\BridalEngagementRepositoryInterface;
use App\Repositories\Contracts\ClientRepositoryInterface;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class BridalEngagementService
{
    public function __construct(
        private BridalEngagementRepositoryInterface $bridalEngagementRepository,
        private ClientRepositoryInterface $clientRepository,
        private BillRepositoryInterface $billRepository,
        private BillingService $billingService,
        private TenantContext $tenantContext,
        private BranchContext $branchContext,
    ) {}

    /** @param array<string, mixed> $data */
    public function createEngagement(array $data): BridalEngagement
    {
        $tenant = $this->tenantContext->get();
        $branch = $this->branchContext->get();

        return DB::transaction(function () use ($data, $tenant, $branch): BridalEngagement {
            $client = $this->resolveClient($data);

            return $this->bridalEngagementRepository->create([
                ...$this->engagementAttributes($data),
                'tenant_id' => $tenant->id,
                'branch_id' => $branch->id,
                'client_id' => $client->id,
                'status' => BridalEngagement::StatusPlanned,
            ]);
        });
    }

    /** @param array<string, mixed> $data */
    public function updateEngagement(BridalEngagement $engagement, array $data): BridalEngagement
    {
        return DB::transaction(function () use ($engagement, $data): BridalEngagement {
            $client = $this->resolveClient($data);

            return $this->bridalEngagementRepository->update($engagement, [
                ...$this->engagementAttributes($data),
                'client_id' => $client->id,
            ]);
        });
    }

    public function deleteEngagement(BridalEngagement $engagement): void
    {
        $this->bridalEngagementRepository->delete($engagement);
    }

    public function markTrialCompleted(BridalEngagement $engagement): BridalEngagement
    {
        $engagement->update(['status' => BridalEngagement::StatusTrialCompleted]);

        return $engagement->refresh();
    }

    public function complete(BridalEngagement $engagement): BridalEngagement
    {
        $engagement->update(['status' => BridalEngagement::StatusCompleted]);

        return $engagement->refresh();
    }

    /**
     * Creates a paid bill for the event. The first line carries the billed
     * amount; every staff line credits that staff member's split towards
     * their target, so the splits need not add up to the bill total.
     *
     * @param  array<int, array{staff_profile_id: int, amount: float|string}>  $staffSplits
     */
    public function createBill(BridalEngagement $engagement, int $createdBy, CarbonInterface $billDate, float $amount, string $paymentMethod, array $staffSplits): Bill
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('The bill amount must be greater than zero.');
        }

        if ($staffSplits === []) {
            throw new InvalidArgumentException('Add at least one servicing staff member.');
        }

        $staffIds = array_column($staffSplits, 'staff_profile_id');

        if (count($staffIds) !== count(array_unique($staffIds))) {
            throw new InvalidArgumentException('A staff member can only be added once.');
        }

        $description = $engagement->event_name
            ? "Bridal makeup - {$engagement->event_name}"
            : 'Bridal makeup';

        $lineItems = [];

        foreach (array_values($staffSplits) as $index => $split) {
            $lineItems[] = [
                'description' => $index === 0 ? $description : "{$description} (staff credit)",
                'quantity' => 1,
                'unit_price' => $index === 0 ? $amount : 0,
                'staff_profile_id' => $split['staff_profile_id'],
                'target_amount' => (string) $split['amount'],
            ];
        }

        return DB::transaction(function () use ($engagement, $createdBy, $billDate, $paymentMethod, $lineItems): Bill {
            $bill = $this->billingService->createManualBill($engagement->client_id, $createdBy, $lineItems, billDate: $billDate);
            $bill = $this->billRepository->update($bill, ['bridal_engagement_id' => $engagement->id]);
            $bill = $this->billingService->recordPayments($bill, [['method' => $paymentMethod, 'amount' => (float) $bill->total]], $createdBy);

            $bill->payments()->update(['created_at' => $billDate, 'updated_at' => $billDate]);

            return $bill;
        });
    }

    public function findBillByNumber(string $number): ?Bill
    {
        return $this->billRepository->findByInvoiceNumber($number);
    }

    public function attachBill(BridalEngagement $engagement, Bill $bill): Bill
    {
        if ($bill->status === Bill::StatusVoid) {
            throw new InvalidArgumentException('A void bill cannot be attached to an event.');
        }

        if ($bill->bridal_engagement_id !== null && $bill->bridal_engagement_id !== $engagement->id) {
            throw new InvalidArgumentException('This bill is already attached to another event.');
        }

        return $this->billRepository->update($bill, ['bridal_engagement_id' => $engagement->id]);
    }

    public function detachBill(BridalEngagement $engagement, Bill $bill): Bill
    {
        if ($bill->bridal_engagement_id !== $engagement->id) {
            throw new InvalidArgumentException('This bill is not attached to this event.');
        }

        return $this->billRepository->update($bill, ['bridal_engagement_id' => null]);
    }

    /**
     * @return array{total: string, advance: string, billed: string, collected: string, outstanding: string}
     */
    public function summarize(BridalEngagement $engagement): array
    {
        $bills = $engagement->bills->where('status', '!=', Bill::StatusVoid);

        $billed = (float) $bills->sum('total');
        $collected = (float) $bills->sum(fn (Bill $bill) => (float) $bill->amount_paid - (float) $bill->amount_refunded);

        return [
            'total' => number_format((float) $engagement->total_amount, 2, '.', ''),
            'advance' => number_format((float) $engagement->advance_amount, 2, '.', ''),
            'billed' => number_format($billed, 2, '.', ''),
            'collected' => number_format($collected, 2, '.', ''),
            'outstanding' => number_format($billed - $collected, 2, '.', ''),
        ];
    }

    /** @param array<string, mixed> $data */
    private function resolveClient(array $data): Client
    {
        $existing = isset($data['client_id'])
            ? $this->clientRepository->findById((int) $data['client_id'])
            : $this->clientRepository->findByPhone($data['contact_number']);

        if ($existing) {
            return $existing;
        }

        return $this->clientRepository->create([
            'tenant_id' => $this->tenantContext->get()->id,
            'name' => $data['bride_name'],
            'phone' => $data['contact_number'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function engagementAttributes(array $data): array
    {
        $isHome = ($data['venue_type'] ?? null) === 'home';
        $hasTrial = (bool) ($data['has_studio_trial'] ?? false);
        $isSaree = ($data['dress_type'] ?? null) === 'saree';

        return [
            'event_name' => $data['event_name'] ?? null,
            'event_date' => $data['event_date'],
            'venue_type' => $data['venue_type'],
            'home_location' => $isHome ? ($data['home_location'] ?? null) : null,
            'has_studio_trial' => $hasTrial,
            'trial_date' => $hasTrial ? ($data['trial_date'] ?? null) : null,
            'ready_time' => $data['ready_time'],
            'total_amount' => $data['total_amount'],
            'advance_amount' => $data['advance_amount'] ?? 0,
            'guest_makeup_count' => $data['guest_makeup_count'] ?? null,
            'groom_makeup' => (bool) ($data['groom_makeup'] ?? false),
            'dress_type' => $data['dress_type'] ?? null,
            'saree_drapist_name' => $isSaree ? ($data['saree_drapist_name'] ?? null) : null,
            'notes' => $data['notes'] ?? null,
        ];
    }
}
