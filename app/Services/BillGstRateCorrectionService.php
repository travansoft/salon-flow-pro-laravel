<?php

namespace App\Services;

use App\Models\Bill;
use App\Models\BillAudit;
use App\Repositories\Contracts\BillLineItemRepositoryInterface;
use App\Repositories\Contracts\BillRepositoryInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class BillGstRateCorrectionService
{
    private const PreviewLimit = 25;

    public function __construct(
        private BillRepositoryInterface $billRepository,
        private BillLineItemRepositoryInterface $billLineItemRepository,
        private BillingService $billingService,
        private BranchContext $branchContext,
    ) {}

    /**
     * Re-states GST-inclusive bills in the date range at a corrected rate.
     * The customer-facing total of every bill is unchanged: only the taxable
     * value and the GST carved out of it move. With $apply false nothing is
     * written, so the same call serves as the preview.
     *
     * @return array{
     *     bills: int,
     *     lines: int,
     *     taxBefore: string,
     *     taxAfter: string,
     *     preview: array<int, array{invoice: string, date: string, taxBefore: string, taxAfter: string, total: string}>,
     *     skipped: array<int, string>,
     * }
     */
    public function correct(string $fromRate, string $toRate, Carbon $from, Carbon $to, bool $apply, ?int $changedBy = null): array
    {
        $this->branchContext->bypass();

        $fromRate = number_format((float) $fromRate, 2, '.', '');
        $toRate = number_format((float) $toRate, 2, '.', '');
        $result = ['bills' => 0, 'lines' => 0, 'taxBefore' => '0.00', 'taxAfter' => '0.00', 'preview' => [], 'skipped' => []];

        foreach ($this->billRepository->inclusiveForRateCorrection($from, $to) as $bill) {
            $plan = $this->plan($bill, $fromRate, $toRate);

            if ($plan === null) {
                continue;
            }

            if ($plan['skipReason'] !== null) {
                $result['skipped'][] = "{$bill->invoiceNumber()}: {$plan['skipReason']}";

                continue;
            }

            if ($apply) {
                DB::transaction(fn () => $this->apply($bill, $plan, $fromRate, $toRate, $changedBy));
            }

            $result['bills']++;
            $result['lines'] += count($plan['lines']);
            $result['taxBefore'] = bcadd($result['taxBefore'], (string) $bill->tax_amount, 2);
            $result['taxAfter'] = bcadd($result['taxAfter'], $plan['bill']['tax_amount'], 2);

            if (count($result['preview']) < self::PreviewLimit) {
                $result['preview'][] = [
                    'invoice' => $bill->invoiceNumber(),
                    'date' => $bill->created_at->format('d-M-Y'),
                    'taxBefore' => (string) $bill->tax_amount,
                    'taxAfter' => $plan['bill']['tax_amount'],
                    'total' => (string) $bill->total,
                ];
            }
        }

        return $result;
    }

    /**
     * @return array{
     *     skipReason: ?string,
     *     lines: array<int, array<string, string>>,
     *     bill: array<string, string>,
     * }|null Null when the bill has no line at the old rate.
     */
    private function plan(Bill $bill, string $fromRate, string $toRate): ?array
    {
        $multiplier = bcadd('1', bcdiv($toRate, '100', 4), 4);
        $lines = [];
        $subtotal = $cgst = $sgst = $igst = '0';

        foreach ($bill->lineItems as $line) {
            $values = [
                'line_total' => (string) $line->line_total,
                'cgst_amount' => (string) $line->cgst_amount,
                'sgst_amount' => (string) $line->sgst_amount,
                'igst_amount' => (string) $line->igst_amount,
            ];

            if (bccomp((string) $line->tax_rate, $fromRate, 2) === 0) {
                $tax = bcadd(bcadd($values['cgst_amount'], $values['sgst_amount'], 2), $values['igst_amount'], 2);
                $inclusiveBase = bcadd($values['line_total'], $tax, 2);
                $newLineTotal = bcadd(bcdiv($inclusiveBase, $multiplier, 10), '0', 2);
                $newTax = bcsub($inclusiveBase, $newLineTotal, 2);
                [$newCgst, $newSgst, $newIgst] = $this->billingService->splitTax($newTax, bccomp($values['igst_amount'], '0', 2) === 0);

                $values = ['line_total' => $newLineTotal, 'cgst_amount' => $newCgst, 'sgst_amount' => $newSgst, 'igst_amount' => $newIgst];
                $lines[$line->id] = [
                    ...$values,
                    'tax_rate' => $toRate,
                    'unit_price' => bcdiv($newLineTotal, (string) $line->quantity, 2),
                ];
            }

            $subtotal = bcadd($subtotal, $values['line_total'], 2);
            $cgst = bcadd($cgst, $values['cgst_amount'], 2);
            $sgst = bcadd($sgst, $values['sgst_amount'], 2);
            $igst = bcadd($igst, $values['igst_amount'], 2);
        }

        if ($lines === []) {
            return null;
        }

        $taxAmount = bcadd(bcadd($cgst, $sgst, 2), $igst, 2);
        $total = bcadd(bcsub($subtotal, (string) $bill->discount_amount, 2), $taxAmount, 2);

        return [
            'skipReason' => bccomp($total, (string) $bill->total, 2) === 0 ? null : "total would change from {$bill->total} to {$total}",
            'lines' => $lines,
            'bill' => [
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'cgst_amount' => $cgst,
                'sgst_amount' => $sgst,
                'igst_amount' => $igst,
                'total' => $total,
            ],
        ];
    }

    /**
     * @param  array{lines: array<int, array<string, string>>, bill: array<string, string>}  $plan
     */
    private function apply(Bill $bill, array $plan, string $fromRate, string $toRate, ?int $changedBy): void
    {
        foreach ($bill->lineItems as $line) {
            if (isset($plan['lines'][$line->id])) {
                $this->billLineItemRepository->update($line, $plan['lines'][$line->id]);
            }
        }

        $this->billRepository->update($bill, $plan['bill']);

        BillAudit::query()->create([
            'tenant_id' => $bill->tenant_id,
            'branch_id' => $bill->branch_id,
            'bill_id' => $bill->id,
            'action' => BillAudit::ActionEdited,
            'field' => 'gst_rate',
            'old_value' => "{$fromRate}%",
            'new_value' => "{$toRate}%",
            'changed_by' => $changedBy,
        ]);
    }
}
