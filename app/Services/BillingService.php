<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Bill;
use App\Models\Client;
use App\Repositories\Contracts\BillRepositoryInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class BillingService
{
    public function __construct(
        private BillRepositoryInterface $billRepository,
        private TenantContext $tenantContext,
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
    public function createManualBill(int $clientId, int $createdBy, array $lineItems, float $discountPercent = 0): Bill
    {
        return $this->createBill($clientId, $createdBy, $lineItems, null, $discountPercent);
    }

    /**
     * @param  array<int, array{description: string, service_id?: int|null, staff_profile_id?: int|null, quantity?: int, unit_price: float, tax_rate?: float}>  $lineItems
     */
    private function createBill(int $clientId, int $createdBy, array $lineItems, ?int $appointmentId = null, float $discountPercent = 0): Bill
    {
        if ($lineItems === []) {
            throw new InvalidArgumentException('A bill must have at least one line item.');
        }

        if ($discountPercent < 0 || $discountPercent > 100) {
            throw new InvalidArgumentException('Discount percentage must be between 0 and 100.');
        }

        $tenant = $this->tenantContext->get();
        $client = Client::query()->findOrFail($clientId);
        $isIntraState = $this->isIntraState($tenant->gst_state_code, $client->gst_number);
        $discountRate = (string) $discountPercent;

        return DB::transaction(function () use ($tenant, $clientId, $createdBy, $lineItems, $appointmentId, $isIntraState, $discountRate): Bill {
            $subtotal = '0';
            $discountAmount = '0';
            $taxAmount = '0';
            $cgstAmount = '0';
            $sgstAmount = '0';
            $igstAmount = '0';

            $resolvedItems = [];
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

                $lineDiscount = bcmul($lineTotal, bcdiv($discountRate, '100', 4), 2);
                $taxableAmount = bcsub($lineTotal, $lineDiscount, 2);

                $lineTax = bccomp($discountRate, '0', 2) === 0
                    ? bcsub($lineTotalInclusive, $lineTotal, 2)
                    : bcmul($taxableAmount, bcdiv($taxRate, '100', 4), 2);

                [$lineCgst, $lineSgst, $lineIgst] = $this->splitTax($lineTax, $isIntraState);

                $subtotal = bcadd($subtotal, $lineTotal, 2);
                $discountAmount = bcadd($discountAmount, $lineDiscount, 2);
                $taxAmount = bcadd($taxAmount, $lineTax, 2);
                $cgstAmount = bcadd($cgstAmount, $lineCgst, 2);
                $sgstAmount = bcadd($sgstAmount, $lineSgst, 2);
                $igstAmount = bcadd($igstAmount, $lineIgst, 2);

                $resolvedItems[] = [
                    'service_id' => $item['service_id'] ?? null,
                    'staff_profile_id' => $item['staff_profile_id'] ?? null,
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

            $total = bcadd(bcsub($subtotal, $discountAmount, 2), $taxAmount, 2);
            $financialYear = FinancialYear::forDate(now());

            $bill = $this->billRepository->create([
                'tenant_id' => $tenant->id,
                'client_id' => $clientId,
                'appointment_id' => $appointmentId,
                'bill_number' => $this->billRepository->nextBillNumber($tenant->id, $financialYear),
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
            ]);

            foreach ($resolvedItems as $item) {
                $bill->lineItems()->create(['tenant_id' => $tenant->id, ...$item]);
            }

            return $bill->load('lineItems');
        });
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

    public function refund(Bill $bill, float $amount, string $reason, int $refundedBy): Bill
    {
        $maxRefundable = bcsub((string) $bill->amount_paid, (string) $bill->amount_refunded, 2);

        if (bccomp((string) $amount, $maxRefundable, 2) > 0) {
            throw new InvalidArgumentException('Refund amount cannot exceed the amount already paid.');
        }

        return DB::transaction(function () use ($bill, $amount, $reason, $refundedBy): Bill {
            $bill->refunds()->create([
                'tenant_id' => $bill->tenant_id,
                'amount' => $amount,
                'reason' => $reason,
                'refunded_by' => $refundedBy,
            ]);

            $this->billRepository->update($bill, [
                'amount_refunded' => bcadd((string) $bill->amount_refunded, (string) $amount, 2),
            ]);

            return $bill->refresh();
        });
    }

    public function void(Bill $bill): Bill
    {
        return $this->billRepository->update($bill, ['status' => Bill::StatusVoid]);
    }
}
