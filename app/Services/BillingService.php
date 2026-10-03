<?php

namespace App\Services;

use App\Actions\ExpandCombo;
use App\Models\Appointment;
use App\Models\Bill;
use App\Models\BillAudit;
use App\Models\BillLineItem;
use App\Models\BillPayment;
use App\Models\Client;
use App\Models\Service;
use App\Repositories\Contracts\BillLineItemRepositoryInterface;
use App\Repositories\Contracts\BillRepositoryInterface;
use App\Repositories\Contracts\StaffProfileRepositoryInterface;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class BillingService
{
    public function __construct(
        private BillRepositoryInterface $billRepository,
        private BillLineItemRepositoryInterface $billLineItemRepository,
        private StaffProfileRepositoryInterface $staffProfileRepository,
        private TenantContext $tenantContext,
        private BranchContext $branchContext,
        private ExpandCombo $expandCombo,
    ) {}

    /**
     * @param  array<int, array{description: string, service_id?: int|null, staff_profile_id?: int|null, quantity?: int, unit_price: float, tax_rate?: float}>  $manualLineItems
     */
    public function generateFromAppointment(Appointment $appointment, int $createdBy, array $manualLineItems = [], float $discountPercent = 0): Bill
    {
        $tenant = $this->tenantContext->get();

        $lineItems = $appointment->services->map(fn ($service) => [
            'service_id' => $service->id,
            'staff_profile_id' => $service->pivot->staff_profile_id,
            'description' => $service->name,
            'quantity' => 1,
            'unit_price' => (float) $service->pivot->price_at_booking,
            'tax_rate' => (float) ($service->tax_rate ?? $tenant->default_gst_rate),
        ])->all();

        return $this->createBill($appointment->client_id, $createdBy, [...$lineItems, ...$manualLineItems], $appointment->id, $discountPercent);
    }

    /**
     * @param  array<int, array{description: string, service_id?: int|null, staff_profile_id?: int|null, quantity?: int, unit_price: float, tax_rate?: float}>  $lineItems
     */
    public function createManualBill(int $clientId, int $createdBy, array $lineItems, float $discountPercent = 0, ?CarbonInterface $billDate = null, ?float $discountAmount = null, ?string $notes = null): Bill
    {
        return $this->createBill($clientId, $createdBy, $lineItems, null, $discountPercent, $billDate, $discountAmount, $notes);
    }

    /**
     * @param  array<int, array{description: string, service_id?: int|null, staff_profile_id?: int|null, quantity?: int, unit_price: float, tax_rate?: float}>  $lineItems
     */
    private function createBill(int $clientId, int $createdBy, array $lineItems, ?int $appointmentId = null, float $discountPercent = 0, ?CarbonInterface $billDate = null, ?float $discountAmount = null, ?string $notes = null): Bill
    {
        if ($lineItems === []) {
            throw new InvalidArgumentException('A bill must have at least one line item.');
        }

        if ($discountPercent < 0 || $discountPercent > 100) {
            throw new InvalidArgumentException('Discount percentage must be between 0 and 100.');
        }

        if ($discountPercent > 0 && $discountAmount !== null) {
            throw new InvalidArgumentException('Provide either a discount percent or a discount amount, not both.');
        }

        if ($discountAmount !== null && $discountAmount < 0) {
            throw new InvalidArgumentException('Discount amount cannot be negative.');
        }

        $tenant = $this->tenantContext->get();
        $branch = $this->branchContext->get();

        if (! $branch) {
            throw new InvalidArgumentException('A branch must be selected before a bill can be created.');
        }

        $lineItems = $this->expandCombos($lineItems);

        $client = Client::query()->findOrFail($clientId);
        $isIntraState = $this->isIntraState($tenant->gst_state_code, $client->gst_number);
        $discountAmountInput = $discountAmount !== null ? (string) $discountAmount : null;
        $discountPercentInput = (string) $discountPercent;

        return DB::transaction(function () use ($tenant, $branch, $clientId, $createdBy, $lineItems, $appointmentId, $isIntraState, $discountPercentInput, $discountAmountInput, $billDate, $notes): Bill {
            $subtotal = '0';
            $subtotalInclusive = '0';
            $lineIntermediates = [];

            foreach ($lineItems as $item) {
                $quantity = $item['quantity'] ?? 1;
                $unitPriceInclusive = (string) $item['unit_price'];
                $taxRate = (string) ($item['tax_rate'] ?? $tenant->default_gst_rate);
                $taxRateMultiplier = bcadd('1', bcdiv($taxRate, '100', 4), 4);

                // Unit price is GST-inclusive. Back-calculate the exclusive rate from
                // the inclusive line total (not the inclusive unit price) so rounding
                // is applied once per line, not once per unit — this keeps quantity > 1
                // exact and avoids the classic "-1 paisa" drift from rounding twice.
                $lineTotalInclusive = bcmul($unitPriceInclusive, (string) $quantity, 2);
                $lineTotal = bcadd(bcdiv($lineTotalInclusive, $taxRateMultiplier, 10), '0', 2);
                $unitPrice = bcdiv($lineTotal, (string) $quantity, 2);

                $subtotal = bcadd($subtotal, $lineTotal, 2);
                $subtotalInclusive = bcadd($subtotalInclusive, $lineTotalInclusive, 2);

                $lineIntermediates[] = [
                    'item' => $item,
                    'quantity' => $quantity,
                    'taxRate' => $taxRate,
                    'lineTotalInclusive' => $lineTotalInclusive,
                    'lineTotal' => $lineTotal,
                    'unitPrice' => $unitPrice,
                ];
            }

            if ($discountAmountInput !== null && bccomp($discountAmountInput, $subtotalInclusive, 2) > 0) {
                throw new InvalidArgumentException('Discount amount cannot exceed the bill subtotal.');
            }

            // Amount mode allocates the exact typed amount across lines by each
            // line's share of the subtotal, with the last line absorbing the
            // rounding remainder so the sum always equals the typed amount
            // exactly — unlike percent mode, this never round-trips through a
            // stored percent, so it can't drift by a paisa from what was typed.
            $lineCount = count($lineIntermediates);
            $allocatedDiscount = '0';

            $discountAmount = '0';
            $taxAmount = '0';
            $cgstAmount = '0';
            $sgstAmount = '0';
            $igstAmount = '0';

            $resolvedItems = [];
            foreach ($lineIntermediates as $index => $line) {
                $item = $line['item'];
                $quantity = $line['quantity'];
                $taxRate = $line['taxRate'];
                $lineTotalInclusive = $line['lineTotalInclusive'];
                $lineTotal = $line['lineTotal'];
                $unitPrice = $line['unitPrice'];

                if ($discountAmountInput !== null) {
                    $isLastLine = $index === $lineCount - 1;
                    $lineDiscount = $isLastLine
                        ? bcsub($discountAmountInput, $allocatedDiscount, 2)
                        : (bccomp($subtotalInclusive, '0', 2) === 0 ? '0' : bcmul($discountAmountInput, bcdiv($lineTotalInclusive, $subtotalInclusive, 10), 2));
                    $allocatedDiscount = bcadd($allocatedDiscount, $lineDiscount, 2);
                } else {
                    $lineDiscount = bcmul($lineTotalInclusive, bcdiv($discountPercentInput, '100', 4), 2);
                }

                $taxableAmount = bcsub($lineTotal, $lineDiscount, 2);

                // The discount is applied to the GST-inclusive amount the customer
                // actually sees, so tax is the remainder needed to make the line's
                // taxable value plus its tax equal (inclusive - discount) exactly —
                // not the taxable value re-multiplied by the tax rate, which would
                // also strip GST off the discount itself and shrink the bill by
                // more than what was typed.
                $lineTax = bcsub(bcsub($lineTotalInclusive, $lineDiscount, 2), $taxableAmount, 2);

                [$lineCgst, $lineSgst, $lineIgst] = $this->splitTax($lineTax, $isIntraState);

                $discountAmount = bcadd($discountAmount, $lineDiscount, 2);
                $taxAmount = bcadd($taxAmount, $lineTax, 2);
                $cgstAmount = bcadd($cgstAmount, $lineCgst, 2);
                $sgstAmount = bcadd($sgstAmount, $lineSgst, 2);
                $igstAmount = bcadd($igstAmount, $lineIgst, 2);

                $resolvedItems[] = [
                    'service_id' => $item['service_id'] ?? null,
                    'combo_service_id' => $item['combo_service_id'] ?? null,
                    'combo_group' => $item['combo_group'] ?? null,
                    'target_amount' => $item['target_amount'] ?? null,
                    'staff_profile_id' => $item['staff_profile_id'] ?? null,
                    'referred_by_staff_profile_id' => $item['referred_by_staff_profile_id'] ?? null,
                    'description' => $item['description'],
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'tax_rate' => $taxRate,
                    'line_total' => $lineTotal,
                    'discount_amount' => $lineDiscount,
                    'cgst_amount' => $lineCgst,
                    'sgst_amount' => $lineSgst,
                    'igst_amount' => $lineIgst,
                ];
            }

            // discount_percent is informational display only in amount mode
            // (e.g. "Discount (10.00%)" on the printed bill) — it is derived
            // from the final discount_amount, never fed back into the per-line
            // math above, so it can't introduce the rounding drift a stored
            // percent would if it were round-tripped through tax calculations.
            $discountRate = $discountAmountInput !== null
                ? (bccomp($subtotalInclusive, '0', 2) === 0 ? '0' : bcmul(bcdiv($discountAmount, $subtotalInclusive, 6), '100', 4))
                : $discountPercentInput;

            $total = bcadd(bcsub($subtotal, $discountAmount, 2), $taxAmount, 2);
            $effectiveDate = $billDate ?? now();
            $financialYear = FinancialYear::forDate($effectiveDate);

            $bill = $this->billRepository->create([
                'tenant_id' => $tenant->id,
                'branch_id' => $branch->id,
                'client_id' => $clientId,
                'appointment_id' => $appointmentId,
                'bill_number' => $this->billRepository->nextBillNumber($tenant->id, $branch->id, $financialYear),
                'financial_year' => $financialYear,
                'subtotal' => $subtotal,
                'discount_percent' => $discountRate,
                'discount_amount' => $discountAmount,
                'tax_amount' => $taxAmount,
                'total' => $total,
                'cgst_amount' => $cgstAmount,
                'sgst_amount' => $sgstAmount,
                'igst_amount' => $igstAmount,
                'status' => Bill::StatusUnpaid,
                'created_by' => $createdBy,
                'notes' => $notes,
            ]);

            if ($billDate) {
                $bill->forceFill(['created_at' => $billDate, 'updated_at' => $billDate])->save();
            }

            foreach ($resolvedItems as $item) {
                $bill->lineItems()->create(['tenant_id' => $tenant->id, 'branch_id' => $branch->id, ...$item]);
            }

            return $bill->load('lineItems');
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $lineItems
     * @return array<int, array<string, mixed>>
     */
    private function expandCombos(array $lineItems): array
    {
        $expanded = [];

        foreach ($lineItems as $item) {
            if (isset($item['components'])) {
                array_push($expanded, ...$this->expandCombo->execute($item));

                continue;
            }

            $expanded[] = $item;
        }

        return $expanded;
    }

    /**
     * Determines place-of-supply: intra-state sales split GST into CGST+SGST,
     * inter-state sales charge IGST instead. A client without a GSTIN is
     * assumed to be a same-state retail customer, the common default.
     */
    private function isIntraState(?string $tenantStateCode, ?string $clientGstNumber): bool
    {
        if (! $tenantStateCode || ! $clientGstNumber) {
            return true;
        }

        return substr($clientGstNumber, 0, 2) === $tenantStateCode;
    }

    /**
     * Splits into CGST + SGST so the two halves always sum back to the exact
     * tax amount. bcdiv truncates rather than rounds, so naively halving both
     * sides can drop a paisa (e.g. 123.55 / 2 = 61.77 + 61.77 = 123.54); SGST
     * is instead the remainder after rounding CGST, never the tax itself.
     *
     * @return array{0: string, 1: string, 2: string}
     */
    private function splitTax(string $taxAmount, bool $isIntraState): array
    {
        if (! $isIntraState) {
            return ['0.00', '0.00', $taxAmount];
        }

        $cgst = bcadd(bcdiv($taxAmount, '2', 10), '0', 2);
        $sgst = bcsub($taxAmount, $cgst, 2);

        return [$cgst, $sgst, '0.00'];
    }

    /**
     * @param  array<int, array{method: string, amount: float}>  $payments
     */
    public function recordPayments(Bill $bill, array $payments, int $receivedBy): Bill
    {
        return DB::transaction(function () use ($bill, $payments, $receivedBy): Bill {
            foreach ($payments as $payment) {
                $bill->payments()->create([
                    'tenant_id' => $bill->tenant_id,
                    'method' => $payment['method'],
                    'amount' => $payment['amount'],
                    'received_by' => $receivedBy,
                ]);
            }

            $totalPaid = bcadd((string) $bill->amount_paid, (string) array_sum(array_column($payments, 'amount')), 2);
            $status = bccomp($totalPaid, (string) $bill->total, 2) >= 0 ? Bill::StatusPaid : Bill::StatusPartial;

            $this->billRepository->update($bill, [
                'amount_paid' => $totalPaid,
                'status' => $status,
            ]);

            return $bill->refresh();
        });
    }

    public function refund(Bill $bill, float $amount, string $reason, int $refundedBy, string $method = BillPayment::MethodCash): Bill
    {
        $maxRefundable = bcsub((string) $bill->amount_paid, (string) $bill->amount_refunded, 2);

        if (bccomp((string) $amount, $maxRefundable, 2) > 0) {
            throw new InvalidArgumentException('Refund amount cannot exceed the amount already paid.');
        }

        return DB::transaction(function () use ($bill, $amount, $reason, $refundedBy, $method): Bill {
            $bill->refunds()->create([
                'tenant_id' => $bill->tenant_id,
                'amount' => $amount,
                'method' => $method,
                'reason' => $reason,
                'refunded_by' => $refundedBy,
            ]);

            $this->billRepository->update($bill, [
                'amount_refunded' => bcadd((string) $bill->amount_refunded, (string) $amount, 2),
            ]);

            return $bill->refresh();
        });
    }

    /**
     * Cancels a bill. Cancelled (void) bills are excluded from reports and
     * revenue totals, and are treated as effectively deleted — the fix for a
     * mis-billed sale is to cancel it and create a new bill, not to keep
     * editing the original.
     */
    public function cancel(Bill $bill, int $changedBy): Bill
    {
        return DB::transaction(function () use ($bill, $changedBy): Bill {
            $updated = $this->billRepository->update($bill, ['status' => Bill::StatusVoid]);

            $this->recordAudit($bill, BillAudit::ActionCancelled, null, null, null, $changedBy);

            return $updated;
        });
    }

    /**
     * Updates the client attached to a bill (e.g. switching a walk-in sale to
     * a named client with a GSTIN for a proper tax invoice), its internal
     * note, its bill date, and the servicing and referring staff of each line,
     * recording each changed field to the audit trail. Line items and totals
     * are otherwise immutable once a bill is created — any other correction
     * requires cancelling the bill and creating a new one.
     *
     * @param  array<int, array{staff_profile_id: int, referred_by_staff_profile_id?: int|null}>  $lineStaff  keyed by line item id
     * @param  array<int, array<int, int|string>>  $comboSplits  staff profile id per component service id, keyed by the id of an old single-line combo to split
     *
     * The bill date may only be moved within its existing financial year:
     * bill_number and financial_year are allocated once at creation and never
     * renumbered, so a date edit crossing a financial year would desync the
     * stored invoice number from the displayed date.
     */
    public function editBill(Bill $bill, int $clientId, ?string $notes, ?CarbonInterface $billDate, int $changedBy, array $lineStaff = [], array $comboSplits = []): Bill
    {
        if ($billDate && $bill->financial_year && FinancialYear::forDate($billDate) !== $bill->financial_year) {
            throw new InvalidArgumentException('The bill date must stay within the bill\'s current financial year ('.$bill->financial_year.').');
        }

        return DB::transaction(function () use ($bill, $clientId, $notes, $billDate, $changedBy, $lineStaff, $comboSplits): Bill {
            $oldClientId = $bill->client_id;
            $oldClientName = $bill->client->name;
            $oldNotes = $bill->notes;
            $oldCreatedAt = $bill->created_at;

            $updated = $this->billRepository->update($bill, [
                'client_id' => $clientId,
                'notes' => $notes,
            ]);

            if ($billDate) {
                $updated->forceFill(['created_at' => $billDate])->save();
            }

            if ($clientId !== $oldClientId) {
                $updated->load('client');
                $this->recordAudit($bill, BillAudit::ActionEdited, 'client', $oldClientName, $updated->client->name, $changedBy);
            }

            if ($notes !== $oldNotes) {
                $this->recordAudit($bill, BillAudit::ActionEdited, 'notes', $oldNotes, $notes, $changedBy);
            }

            if ($billDate && ! $billDate->equalTo($oldCreatedAt)) {
                $this->recordAudit($bill, BillAudit::ActionEdited, 'bill_date', $oldCreatedAt->toDateString(), $billDate->toDateString(), $changedBy);
            }

            $lineStaff = $this->splitComboLines($bill, $comboSplits, $lineStaff, $changedBy);

            $this->updateLineStaff($bill, $lineStaff, $changedBy);

            return $updated;
        });
    }

    /** @param array<int, array{staff_profile_id: int, referred_by_staff_profile_id?: int|null}> $lineStaff keyed by line item id */
    private function updateLineStaff(Bill $bill, array $lineStaff, int $changedBy): void
    {
        $bill->loadMissing(['lineItems.staffProfile', 'lineItems.referredByStaffProfile']);

        $comboReferrers = $this->changedComboReferrers($bill, $lineStaff);

        foreach ($bill->lineItems as $lineItem) {
            if (! isset($lineStaff[$lineItem->id])) {
                continue;
            }

            $newServicingId = (int) $lineStaff[$lineItem->id]['staff_profile_id'];
            $newReferrerId = $lineStaff[$lineItem->id]['referred_by_staff_profile_id'] ?? null;
            $newReferrerId = $newReferrerId === null ? null : (int) $newReferrerId;

            if ($lineItem->combo_group !== null && array_key_exists($lineItem->combo_group, $comboReferrers)) {
                $newReferrerId = $comboReferrers[$lineItem->combo_group];
            }

            if ($newServicingId === $lineItem->staff_profile_id && $newReferrerId === $lineItem->referred_by_staff_profile_id) {
                continue;
            }

            $oldServicingId = $lineItem->staff_profile_id;
            $oldReferrerId = $lineItem->referred_by_staff_profile_id;
            $oldServicingName = $lineItem->staffProfile?->name ?? 'None';
            $oldReferrerName = $lineItem->referredByStaffProfile?->name ?? 'Direct';

            $this->billLineItemRepository->update($lineItem, [
                'staff_profile_id' => $newServicingId,
                'referred_by_staff_profile_id' => $newReferrerId,
            ]);

            if ($newServicingId !== $oldServicingId) {
                $this->recordAudit($bill, BillAudit::ActionEdited, 'servicing_staff', "{$lineItem->description}: {$oldServicingName}", "{$lineItem->description}: {$this->staffName($newServicingId)}", $changedBy);
            }

            if ($newReferrerId !== $oldReferrerId) {
                $newReferrerName = $newReferrerId === null ? 'Direct' : $this->staffName($newReferrerId);

                $this->recordAudit($bill, BillAudit::ActionEdited, 'referring_staff', "{$lineItem->description}: {$oldReferrerName}", "{$lineItem->description}: {$newReferrerName}", $changedBy);
            }
        }
    }

    /**
     * Replaces a combo that was billed as one line (before combos were split
     * per service) with one line per component, carrying the staff given for
     * each. The line's GST-inclusive value and discount are spread over the components
     * in proportion to their combo prices, so the payable total never changes.
     *
     * @param  array<int, array<int, int|string>>  $comboSplits
     * @param  array<int, array{staff_profile_id: int, referred_by_staff_profile_id?: int|null}>  $lineStaff
     * @return array<int, array{staff_profile_id: int, referred_by_staff_profile_id?: int|null}>
     */
    private function splitComboLines(Bill $bill, array $comboSplits, array $lineStaff, int $changedBy): array
    {
        if ($comboSplits === []) {
            return $lineStaff;
        }

        $bill->loadMissing(['lineItems.service.comboItems.component.staff']);

        foreach ($comboSplits as $lineItemId => $staffByComponent) {
            $lineItem = $bill->lineItems->firstWhere('id', (int) $lineItemId);
            $combo = $lineItem?->service;

            if (! $lineItem || ! $combo?->is_combo || $lineItem->combo_group !== null) {
                throw new InvalidArgumentException('Only a combo billed as a single line can be split.');
            }

            $referrerId = array_key_exists('referred_by_staff_profile_id', $lineStaff[$lineItemId] ?? [])
                ? $lineStaff[$lineItemId]['referred_by_staff_profile_id']
                : $lineItem->referred_by_staff_profile_id;

            $this->splitComboLine($lineItem, $combo, array_map('intval', $staffByComponent), $referrerId === null || $referrerId === '' ? null : (int) $referrerId);

            $this->recordAudit($bill, BillAudit::ActionEdited, 'combo_split', "{$lineItem->description}: single line", $this->describeSplit($combo, $staffByComponent), $changedBy);

            unset($lineStaff[$lineItemId]);
        }

        $this->refreshBillTotalsFromLines($bill);

        $bill->unsetRelation('lineItems');

        return $lineStaff;
    }

    /** @param array<int, int> $staffByComponent */
    private function splitComboLine(BillLineItem $lineItem, Service $combo, array $staffByComponent, ?int $referrerId): void
    {
        $comboItems = $combo->comboItems;

        if ($comboItems->pluck('component_service_id')->sort()->values()->all() !== collect($staffByComponent)->keys()->map(fn ($id): int => (int) $id)->sort()->values()->all()) {
            throw new InvalidArgumentException("Select a staff member for every service in \"{$combo->name}\".");
        }

        $weightTotal = $comboItems->reduce(fn (string $sum, $comboItem): string => bcadd($sum, (string) $comboItem->price, 2), '0');
        $discount = (string) $lineItem->discount_amount;
        $tax = bcadd(bcadd((string) $lineItem->cgst_amount, (string) $lineItem->sgst_amount, 2), (string) $lineItem->igst_amount, 2);
        $inclusiveBeforeDiscount = bcadd(bcadd(bcsub((string) $lineItem->line_total, $discount, 2), $tax, 2), $discount, 2);
        $isIntraState = bccomp((string) $lineItem->igst_amount, '0', 2) === 0;
        $taxRateMultiplier = bcadd('1', bcdiv((string) $lineItem->tax_rate, '100', 4), 4);
        $quantity = (string) $lineItem->quantity;
        $group = (string) Str::uuid();
        $lastIndex = $comboItems->count() - 1;
        $remainingInclusive = $inclusiveBeforeDiscount;
        $remainingDiscount = $discount;

        foreach ($comboItems as $index => $comboItem) {
            $component = $comboItem->component;
            $staffProfileId = $staffByComponent[$comboItem->component_service_id];

            if (! $component->staff->contains('id', $staffProfileId)) {
                throw new InvalidArgumentException("The selected staff member is not eligible to perform \"{$component->name}\".");
            }

            $isLast = $index === $lastIndex;

            $lineInclusive = match (true) {
                $isLast => $remainingInclusive,
                bccomp($weightTotal, '0', 2) === 0 => $this->roundMoney(bcdiv($inclusiveBeforeDiscount, (string) ($lastIndex + 1), 6)),
                default => $this->roundMoney(bcmul($inclusiveBeforeDiscount, bcdiv((string) $comboItem->price, $weightTotal, 10), 6)),
            };

            $lineDiscount = match (true) {
                $isLast => $remainingDiscount,
                bccomp($inclusiveBeforeDiscount, '0', 2) === 0 => '0.00',
                default => $this->roundMoney(bcmul($discount, bcdiv($lineInclusive, $inclusiveBeforeDiscount, 10), 6)),
            };

            $remainingInclusive = bcsub($remainingInclusive, $lineInclusive, 2);
            $remainingDiscount = bcsub($remainingDiscount, $lineDiscount, 2);

            $lineTotal = bcadd(bcdiv($lineInclusive, $taxRateMultiplier, 10), '0', 2);
            $lineTax = bcsub(bcsub($lineInclusive, $lineDiscount, 2), bcsub($lineTotal, $lineDiscount, 2), 2);
            [$lineCgst, $lineSgst, $lineIgst] = $this->splitTax($lineTax, $isIntraState);

            $this->billLineItemRepository->create([
                'tenant_id' => $lineItem->tenant_id,
                'branch_id' => $lineItem->branch_id,
                'bill_id' => $lineItem->bill_id,
                'service_id' => $component->id,
                'combo_service_id' => $combo->id,
                'combo_group' => $group,
                'target_amount' => $lineInclusive,
                'staff_profile_id' => $staffProfileId,
                'referred_by_staff_profile_id' => $referrerId,
                'description' => "{$combo->name} - {$component->name}",
                'quantity' => $lineItem->quantity,
                'unit_price' => bcdiv($lineTotal, $quantity, 2),
                'tax_rate' => $lineItem->tax_rate,
                'line_total' => $lineTotal,
                'discount_amount' => $lineDiscount,
                'cgst_amount' => $lineCgst,
                'sgst_amount' => $lineSgst,
                'igst_amount' => $lineIgst,
            ]);
        }

        $this->billLineItemRepository->delete($lineItem);
    }

    private function roundMoney(string $amount): string
    {
        return bcadd($amount, '0.005', 2);
    }

    /**
     * Brings the bill's stored subtotal and GST back in line with its lines
     * after a split, since each service now has its own rounded GST. The
     * payable total is unchanged because every line keeps its inclusive value.
     */
    private function refreshBillTotalsFromLines(Bill $bill): void
    {
        $lines = $bill->lineItems()->get();
        $sum = fn (string $field): string => $lines->reduce(fn (string $carry, BillLineItem $line): string => bcadd($carry, (string) $line->{$field}, 2), '0');

        $subtotal = $sum('line_total');
        $cgst = $sum('cgst_amount');
        $sgst = $sum('sgst_amount');
        $igst = $sum('igst_amount');
        $taxAmount = bcadd(bcadd($cgst, $sgst, 2), $igst, 2);

        $this->billRepository->update($bill, [
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'cgst_amount' => $cgst,
            'sgst_amount' => $sgst,
            'igst_amount' => $igst,
            'total' => bcadd(bcsub($subtotal, (string) $bill->discount_amount, 2), $taxAmount, 2),
        ]);
    }

    /** @param array<int, int|string> $staffByComponent */
    private function describeSplit(Service $combo, array $staffByComponent): string
    {
        return $combo->comboItems
            ->map(fn ($comboItem): string => "{$comboItem->component->name}: {$this->staffName((int) $staffByComponent[$comboItem->component_service_id])}")
            ->implode(', ');
    }

    /**
     * Referral belongs to the whole combo, so a referrer changed on any one of
     * its lines is applied to every line of that combo.
     *
     * @param  array<int, array{staff_profile_id: int, referred_by_staff_profile_id?: int|null}>  $lineStaff
     * @return array<string, int|null>
     */
    private function changedComboReferrers(Bill $bill, array $lineStaff): array
    {
        $comboReferrers = [];

        foreach ($bill->lineItems as $lineItem) {
            if ($lineItem->combo_group === null || ! isset($lineStaff[$lineItem->id])) {
                continue;
            }

            $submitted = $lineStaff[$lineItem->id]['referred_by_staff_profile_id'] ?? null;
            $submitted = $submitted === null ? null : (int) $submitted;

            if ($submitted !== $lineItem->referred_by_staff_profile_id) {
                $comboReferrers[$lineItem->combo_group] = $submitted;
            }
        }

        return $comboReferrers;
    }

    private function staffName(int $staffProfileId): ?string
    {
        return $this->staffProfileRepository->findById($staffProfileId)?->name;
    }

    private function recordAudit(Bill $bill, string $action, ?string $field, ?string $oldValue, ?string $newValue, int $changedBy): void
    {
        BillAudit::query()->create([
            'tenant_id' => $bill->tenant_id,
            'branch_id' => $bill->branch_id,
            'bill_id' => $bill->id,
            'action' => $action,
            'field' => $field,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'changed_by' => $changedBy,
        ]);
    }
}
