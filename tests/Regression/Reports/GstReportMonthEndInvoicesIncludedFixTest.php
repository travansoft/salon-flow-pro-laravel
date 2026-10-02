<?php

namespace Tests\Regression\Reports;

use App\Models\Bill;
use App\Models\Branch;
use App\Models\Tenant;
use App\Services\BranchContext;
use App\Services\GstReportService;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class GstReportMonthEndInvoicesIncludedFixTest extends TestCase
{
    use RefreshDatabase;

    public function test_invoice_created_late_on_last_day_of_month_is_not_dropped_from_gst_totals(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        app(BranchContext::class)->set(Branch::factory()->create(['tenant_id' => $tenant->id]));

        Bill::factory()->create(['tenant_id' => $tenant->id, 'created_at' => '2026-02-28 23:30:00']);

        $report = app(GstReportService::class)->forMonth(Carbon::parse('2026-02-01'));

        $this->assertSame('90.00', $report['totals']['tax']);
    }
}
