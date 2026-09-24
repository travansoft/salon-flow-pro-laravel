<?php

namespace Tests\Unit\Reports;

use App\Models\Bill;
use App\Models\BillLineItem;
use App\Models\BillPayment;
use App\Models\Branch;
use App\Models\Product;
use App\Models\Tenant;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Services\BranchContext;
use App\Services\ReportService;
use App\Services\TenantContext;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Mockery;
use Tests\TestCase;

class ReportServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_for_sums_revenue_and_excludes_void_bills(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);

        $today = Carbon::today();

        $paidBill = Bill::factory()->paid()->create([
            'tenant_id' => $tenant->id,
            'total' => 590,
            'created_at' => $today,
        ]);
        BillLineItem::factory()->create([
            'tenant_id' => $tenant->id,
            'bill_id' => $paidBill->id,
            'line_total' => 500,
            'description' => 'Haircut',
        ]);
        BillPayment::factory()->create([
            'tenant_id' => $tenant->id,
            'bill_id' => $paidBill->id,
            'method' => BillPayment::MethodCash,
            'amount' => 590,
        ]);

        Bill::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => Bill::StatusVoid,
            'total' => 10000,
            'created_at' => $today,
        ]);

        $productRepository = Mockery::mock(ProductRepositoryInterface::class);
        $productRepository->shouldReceive('getLowStock')->once()->andReturn(new Collection);

        $service = new ReportService($productRepository);
        $report = $service->reportFor($today, $today);

        $this->assertSame('590.00', $report['totalRevenue']);
        $this->assertSame(1, $report['billCount']);
        $this->assertSame(0, $report['lowStockCount']);
    }

    public function test_report_for_reports_low_stock_count_from_repository(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);

        $productRepository = Mockery::mock(ProductRepositoryInterface::class);
        $productRepository->shouldReceive('getLowStock')->once()->andReturn(new Collection([
            Product::factory()->make(),
            Product::factory()->make(),
        ]));

        $service = new ReportService($productRepository);
        $report = $service->reportFor(Carbon::today(), Carbon::today());

        $this->assertSame(2, $report['lowStockCount']);
    }
}
