<?php

namespace App\Services;

use App\Models\Bill;
use App\Repositories\Contracts\BillRepositoryInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class GstReportService
{
    public const Headings = [
        'Invoice #', 'Date', 'Client', 'Client GSTIN', 'Taxable value', 'CGST', 'SGST', 'IGST', 'Total tax', 'Invoice total',
    ];

    public function __construct(private BillRepositoryInterface $billRepository) {}

    /**
     * @return array{
     *     invoices: Collection<int, array<string, mixed>>,
     *     rateSummary: array<string, array{taxable: string, cgst: string, sgst: string, igst: string}>,
     *     totals: array{taxable: string, cgst: string, sgst: string, igst: string, tax: string, total: string},
     * }
     */
    public function forMonth(Carbon $month): array
    {
        $bills = $this->billRepository->forGstReport($month->copy()->startOfMonth(), $month->copy()->endOfMonth());

        return [
            'invoices' => $bills->map(fn (Bill $bill): array => $this->invoiceRow($bill)),
            'rateSummary' => $this->rateSummary($bills),
            'totals' => $this->totals($bills),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $invoices
     * @return array<int, array<int, string|float|null>>
     */
    public function exportRows(Collection $invoices): array
    {
        return $invoices->map(fn (array $invoice): array => [
            $invoice['invoice_number'],
            $invoice['date']->format('d M Y'),
            $invoice['client'],
            $invoice['gstin'],
            (float) $invoice['taxable'],
            (float) $invoice['cgst'],
            (float) $invoice['sgst'],
            (float) $invoice['igst'],
            (float) $invoice['tax'],
            (float) $invoice['total'],
        ])->all();
    }

    /** @return array<string, mixed> */
    private function invoiceRow(Bill $bill): array
    {
        return [
            'bill_id' => $bill->id,
            'invoice_number' => $bill->invoiceNumber(),
            'date' => $bill->created_at,
            'client' => $bill->client?->name ?? 'Walk-in',
            'gstin' => $bill->client?->gst_number,
            'taxable' => bcsub((string) $bill->subtotal, (string) $bill->discount_amount, 2),
            'cgst' => (string) $bill->cgst_amount,
            'sgst' => (string) $bill->sgst_amount,
            'igst' => (string) $bill->igst_amount,
            'tax' => (string) $bill->tax_amount,
            'total' => (string) $bill->total,
        ];
    }

    /**
     * @param  Collection<int, Bill>  $bills
     * @return array<string, array{taxable: string, cgst: string, sgst: string, igst: string}>
     */
    private function rateSummary(Collection $bills): array
    {
        $summary = [];

        foreach ($bills as $bill) {
            foreach ($bill->gstBreakdownByRate() as $rate => $amounts) {
                $summary[$rate] ??= ['taxable' => '0.00', 'cgst' => '0.00', 'sgst' => '0.00', 'igst' => '0.00'];

                foreach ($amounts as $key => $amount) {
                    $summary[$rate][$key] = bcadd($summary[$rate][$key], $amount, 2);
                }
            }
        }

        ksort($summary, SORT_NUMERIC);

        return $summary;
    }

    /**
     * @param  Collection<int, Bill>  $bills
     * @return array{taxable: string, cgst: string, sgst: string, igst: string, tax: string, total: string}
     */
    private function totals(Collection $bills): array
    {
        $totals = ['taxable' => '0.00', 'cgst' => '0.00', 'sgst' => '0.00', 'igst' => '0.00', 'tax' => '0.00', 'total' => '0.00'];

        foreach ($bills as $bill) {
            $totals['taxable'] = bcadd($totals['taxable'], bcsub((string) $bill->subtotal, (string) $bill->discount_amount, 2), 2);
            $totals['cgst'] = bcadd($totals['cgst'], (string) $bill->cgst_amount, 2);
            $totals['sgst'] = bcadd($totals['sgst'], (string) $bill->sgst_amount, 2);
            $totals['igst'] = bcadd($totals['igst'], (string) $bill->igst_amount, 2);
            $totals['tax'] = bcadd($totals['tax'], (string) $bill->tax_amount, 2);
            $totals['total'] = bcadd($totals['total'], (string) $bill->total, 2);
        }

        return $totals;
    }
}
