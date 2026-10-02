<?php

namespace Tests\Unit\Reports;

use App\Models\Bill;
use App\Models\BillLineItem;
use App\Models\Client;
use App\Repositories\Contracts\BillRepositoryInterface;
use App\Services\GstReportService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Mockery;
use Tests\TestCase;

class GstReportServiceTest extends TestCase
{
    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int, array<string, mixed>>  $lines
     */
    private function bill(array $attributes, array $lines = []): Bill
    {
        $bill = new Bill($attributes);
        $bill->id = $attributes['id'] ?? 1;
        $bill->created_at = Carbon::parse('2026-09-10 11:00');
        $bill->setRelation('client', new Client(['name' => 'Asha', 'gst_number' => '32ABCDE1234F1Z5']));
        $bill->setRelation('lineItems', new Collection(array_map(fn (array $line) => new BillLineItem($line), $lines)));

        return $bill;
    }

    private function serviceReturning(Collection $bills): GstReportService
    {
        $repository = Mockery::mock(BillRepositoryInterface::class);
        $repository->shouldReceive('forGstReport')->once()->andReturn($bills);

        return new GstReportService($repository);
    }

    public function test_invoice_row_taxable_value_is_subtotal_less_discount(): void
    {
        $bill = $this->bill([
            'bill_number' => 5, 'subtotal' => '1000.00', 'discount_amount' => '100.00', 'tax_amount' => '162.00',
            'cgst_amount' => '81.00', 'sgst_amount' => '81.00', 'igst_amount' => '0.00', 'total' => '1062.00',
        ]);

        $report = $this->serviceReturning(new Collection([$bill]))->forMonth(Carbon::parse('2026-09-01'));

        $this->assertSame('900.00', $report['invoices'][0]['taxable']);
        $this->assertSame('32ABCDE1234F1Z5', $report['invoices'][0]['gstin']);
        $this->assertSame('1062.00', $report['invoices'][0]['total']);
    }

    public function test_totals_sum_every_invoice(): void
    {
        $first = $this->bill(['id' => 1, 'bill_number' => 1, 'subtotal' => '500.00', 'discount_amount' => '0.00', 'tax_amount' => '90.00', 'cgst_amount' => '45.00', 'sgst_amount' => '45.00', 'igst_amount' => '0.00', 'total' => '590.00']);
        $second = $this->bill(['id' => 2, 'bill_number' => 2, 'subtotal' => '200.00', 'discount_amount' => '0.00', 'tax_amount' => '36.00', 'cgst_amount' => '0.00', 'sgst_amount' => '0.00', 'igst_amount' => '36.00', 'total' => '236.00']);

        $report = $this->serviceReturning(new Collection([$first, $second]))->forMonth(Carbon::parse('2026-09-01'));

        $this->assertSame('700.00', $report['totals']['taxable']);
        $this->assertSame('45.00', $report['totals']['cgst']);
        $this->assertSame('36.00', $report['totals']['igst']);
        $this->assertSame('126.00', $report['totals']['tax']);
        $this->assertSame('826.00', $report['totals']['total']);
    }

    public function test_rate_summary_groups_line_items_by_rate_across_bills(): void
    {
        $line = fn (string $rate, string $total, string $half) => [
            'tax_rate' => $rate, 'line_total' => $total, 'discount_amount' => '0.00', 'cgst_amount' => $half, 'sgst_amount' => $half, 'igst_amount' => '0.00',
        ];
        $base = ['subtotal' => '0', 'discount_amount' => '0', 'tax_amount' => '0', 'cgst_amount' => '0', 'sgst_amount' => '0', 'igst_amount' => '0', 'total' => '0'];

        $first = $this->bill($base + ['id' => 1, 'bill_number' => 1], [$line('18', '100.00', '9.00'), $line('5', '100.00', '2.50')]);
        $second = $this->bill($base + ['id' => 2, 'bill_number' => 2], [$line('18', '200.00', '18.00')]);

        $report = $this->serviceReturning(new Collection([$first, $second]))->forMonth(Carbon::parse('2026-09-01'));

        $this->assertSame([5.0, 18.0], array_map('floatval', array_keys($report['rateSummary'])));
        $this->assertSame('300.00', $report['rateSummary']['18.00']['taxable']);
        $this->assertSame('27.00', $report['rateSummary']['18.00']['cgst']);
    }

    public function test_empty_month_returns_zero_totals(): void
    {
        $report = $this->serviceReturning(new Collection)->forMonth(Carbon::parse('2026-09-01'));

        $this->assertCount(0, $report['invoices']);
        $this->assertSame([], $report['rateSummary']);
        $this->assertSame('0.00', $report['totals']['tax']);
    }

    public function test_export_rows_match_headings_width_and_use_numbers(): void
    {
        $bill = $this->bill(['bill_number' => 5, 'subtotal' => '500.00', 'discount_amount' => '0.00', 'tax_amount' => '90.00', 'cgst_amount' => '45.00', 'sgst_amount' => '45.00', 'igst_amount' => '0.00', 'total' => '590.00']);
        $service = $this->serviceReturning(new Collection([$bill]));

        $rows = $service->exportRows($service->forMonth(Carbon::parse('2026-09-01'))['invoices']);

        $this->assertCount(count(GstReportService::Headings), $rows[0]);
        $this->assertSame(590.0, $rows[0][9]);
        $this->assertSame('10 Sep 2026', $rows[0][1]);
    }
}
