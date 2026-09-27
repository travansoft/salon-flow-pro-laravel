<?php

namespace Tests\Integration\Reports;

use App\Models\Appointment;
use App\Models\Bill;
use App\Models\BillLineItem;
use App\Models\BillPayment;
use App\Models\Branch;
use App\Models\Product;
use App\Models\StaffProfile;
use App\Models\Tenant;
use App\Models\User;
use App\Services\BranchContext;
use App\Services\ReportService;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ReportServiceCalculationIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_aggregates_revenue_top_services_payment_mix_and_staff_performance(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);

        $today = Carbon::today();

        $staffUser = User::factory()->for($tenant)->create(['name' => 'Priya Nair']);
        $staffProfile = StaffProfile::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $staffUser->id]);
        $appointment = Appointment::factory()->create([
            'tenant_id' => $tenant->id,
            'staff_profile_id' => $staffProfile->id,
            'status' => Appointment::StatusCompleted,
        ]);

        $bill = Bill::factory()->paid()->create([
            'tenant_id' => $tenant->id,
            'appointment_id' => $appointment->id,
            'total' => 1000,
            'created_at' => $today,
        ]);
        BillLineItem::factory()->create([
            'tenant_id' => $tenant->id,
            'bill_id' => $bill->id,
            'description' => 'Hair spa',
            'line_total' => 1000,
        ]);
        BillPayment::factory()->create([
            'tenant_id' => $tenant->id,
            'bill_id' => $bill->id,
            'method' => BillPayment::MethodUpi,
            'amount' => 1000,
        ]);

        Product::factory()->create([
            'tenant_id' => $tenant->id,
            'quantity_on_hand' => 1,
            'reorder_level' => 5,
        ]);

        $report = app(ReportService::class)->reportFor($today, $today);

        $this->assertSame('1000.00', $report['totalRevenue']);
        $this->assertSame(1, $report['billCount']);
        $this->assertSame(1, $report['lowStockCount']);
        $this->assertSame('Hair spa', $report['topServices'][0]['name']);
        $this->assertSame('1000.00', $report['topServices'][0]['amount']);
        $this->assertSame('upi', $report['paymentMix'][0]['method']);
        $this->assertSame('1000.00', $report['paymentMix'][0]['amount']);
        $this->assertSame('Priya Nair', $report['staffPerformance'][0]['name']);
        $this->assertSame('1000.00', $report['staffPerformance'][0]['revenue']);
        $this->assertSame(1, $report['staffPerformance'][0]['services']);
    }
}
