<?php

namespace Tests\Integration\Reports;

use App\Models\Bill;
use App\Models\BillLineItem;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Tenant;
use App\Services\BranchContext;
use App\Services\GstReportService;
use App\Services\TenantContext;
use App\Services\XlsxWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;
use ZipArchive;

class GstReportDatabaseTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($this->tenant);
        app(BranchContext::class)->set(Branch::factory()->create(['tenant_id' => $this->tenant->id]));
    }

    public function test_report_reads_month_bills_from_database_and_skips_void(): void
    {
        $client = Client::factory()->create(['tenant_id' => $this->tenant->id, 'gst_number' => '32ABCDE1234F1Z5']);
        $kept = Bill::factory()->create(['tenant_id' => $this->tenant->id, 'client_id' => $client->id, 'created_at' => '2026-09-05 10:00:00']);
        Bill::factory()->create(['tenant_id' => $this->tenant->id, 'status' => Bill::StatusVoid, 'created_at' => '2026-09-06 10:00:00']);
        Bill::factory()->create(['tenant_id' => $this->tenant->id, 'created_at' => '2026-10-01 00:00:00']);
        BillLineItem::factory()->create([
            'tenant_id' => $this->tenant->id, 'bill_id' => $kept->id, 'tax_rate' => 18, 'line_total' => 500,
            'discount_amount' => 0, 'cgst_amount' => 45, 'sgst_amount' => 45, 'igst_amount' => 0,
        ]);

        $report = app(GstReportService::class)->forMonth(Carbon::parse('2026-09-01'));

        $this->assertCount(1, $report['invoices']);
        $this->assertSame('32ABCDE1234F1Z5', $report['invoices'][0]['gstin']);
        $this->assertSame('90.00', $report['totals']['tax']);
        $this->assertSame('500.00', $report['rateSummary']['18.00']['taxable']);
    }

    public function test_month_boundaries_include_first_and_last_day(): void
    {
        Bill::factory()->create(['tenant_id' => $this->tenant->id, 'created_at' => '2026-09-01 00:00:00']);
        Bill::factory()->create(['tenant_id' => $this->tenant->id, 'created_at' => '2026-09-30 23:59:59']);

        $report = app(GstReportService::class)->forMonth(Carbon::parse('2026-09-01'));

        $this->assertCount(2, $report['invoices']);
    }

    public function test_export_produces_a_valid_xlsx_with_invoice_rows(): void
    {
        Bill::factory()->create(['tenant_id' => $this->tenant->id, 'created_at' => '2026-09-05 10:00:00']);
        $service = app(GstReportService::class);

        $path = app(XlsxWriter::class)->build(
            'GST report',
            GstReportService::Headings,
            $service->exportRows($service->forMonth(Carbon::parse('2026-09-01'))['invoices']),
        );

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path));
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        unlink($path);

        $this->assertStringContainsString('Taxable value', $sheet);
        $this->assertStringContainsString('<v>590</v>', $sheet);
    }
}
