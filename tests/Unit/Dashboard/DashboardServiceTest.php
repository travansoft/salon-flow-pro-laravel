<?php

namespace Tests\Unit\Dashboard;

use App\Models\Bill;
use App\Models\BillLineItem;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\Tenant;
use App\Models\User;
use App\Services\BranchContext;
use App\Services\DashboardService;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DashboardServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_summary_computes_todays_revenue_customers_and_average_bill(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);

        $clientA = Client::factory()->create(['tenant_id' => $tenant->id]);
        $clientB = Client::factory()->create(['tenant_id' => $tenant->id]);
        $user = User::factory()->for($tenant)->create();

        Bill::factory()->create([
            'tenant_id' => $tenant->id,
            'client_id' => $clientA->id,
            'created_by' => $user->id,
            'total' => 300,
            'created_at' => now(),
        ]);
        Bill::factory()->create([
            'tenant_id' => $tenant->id,
            'client_id' => $clientB->id,
            'created_by' => $user->id,
            'total' => 200,
            'created_at' => now(),
        ]);

        $summary = app(DashboardService::class)->summaryFor(Carbon::today());

        $this->assertSame('500.00', $summary['todaysRevenue']);
        $this->assertSame(2, $summary['customerCount']);
        $this->assertSame('250.00', $summary['averageBill']);
    }

    public function test_summary_excludes_voided_bills_and_bills_from_other_days(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);

        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $user = User::factory()->for($tenant)->create();

        Bill::factory()->create([
            'tenant_id' => $tenant->id,
            'client_id' => $client->id,
            'created_by' => $user->id,
            'total' => 999,
            'status' => Bill::StatusVoid,
            'created_at' => now(),
        ]);
        Bill::factory()->create([
            'tenant_id' => $tenant->id,
            'client_id' => $client->id,
            'created_by' => $user->id,
            'total' => 999,
            'created_at' => now()->subDay(),
        ]);

        $summary = app(DashboardService::class)->summaryFor(Carbon::today());

        $this->assertSame('0.00', $summary['todaysRevenue']);
        $this->assertSame(0, $summary['customerCount']);
    }

    public function test_summary_identifies_top_employee_by_revenue_from_bills_today(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);

        $staffTopUser = User::factory()->for($tenant)->create();
        $staffTop = StaffProfile::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $staffTopUser->id]);
        $staffOtherUser = User::factory()->for($tenant)->create();
        $staffOther = StaffProfile::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $staffOtherUser->id]);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $user = User::factory()->for($tenant)->create();

        $topBill = Bill::factory()->create([
            'tenant_id' => $tenant->id,
            'client_id' => $client->id,
            'created_by' => $user->id,
            'total' => 900,
            'created_at' => now(),
        ]);
        BillLineItem::factory()->create([
            'tenant_id' => $tenant->id,
            'bill_id' => $topBill->id,
            'staff_profile_id' => $staffTop->id,
            'line_total' => 900,
        ]);

        $otherBill = Bill::factory()->create([
            'tenant_id' => $tenant->id,
            'client_id' => $client->id,
            'created_by' => $user->id,
            'total' => 100,
            'created_at' => now(),
        ]);
        BillLineItem::factory()->create([
            'tenant_id' => $tenant->id,
            'bill_id' => $otherBill->id,
            'staff_profile_id' => $staffOther->id,
            'line_total' => 100,
        ]);

        $summary = app(DashboardService::class)->summaryFor(Carbon::today());

        $this->assertSame($staffTopUser->name, $summary['topEmployee']['name']);
        $this->assertSame('900.00', $summary['topEmployee']['revenue']);
    }

    public function test_summary_identifies_top_service_by_quantity_billed_today(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);

        $topService = Service::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Hair Colour']);
        $otherService = Service::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Manicure']);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $user = User::factory()->for($tenant)->create();

        $bill = Bill::factory()->create([
            'tenant_id' => $tenant->id,
            'client_id' => $client->id,
            'created_by' => $user->id,
            'created_at' => now(),
        ]);

        BillLineItem::factory()->create([
            'tenant_id' => $tenant->id,
            'bill_id' => $bill->id,
            'service_id' => $topService->id,
            'quantity' => 3,
        ]);
        BillLineItem::factory()->create([
            'tenant_id' => $tenant->id,
            'bill_id' => $bill->id,
            'service_id' => $otherService->id,
            'quantity' => 1,
        ]);

        $summary = app(DashboardService::class)->summaryFor(Carbon::today());

        $this->assertSame('Hair Colour', $summary['topService']);
    }

    public function test_summary_sums_balance_due_across_unpaid_and_partial_bills(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);

        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $user = User::factory()->for($tenant)->create();

        Bill::factory()->create([
            'tenant_id' => $tenant->id,
            'client_id' => $client->id,
            'created_by' => $user->id,
            'total' => 500,
            'amount_paid' => 0,
            'status' => Bill::StatusUnpaid,
        ]);
        Bill::factory()->create([
            'tenant_id' => $tenant->id,
            'client_id' => $client->id,
            'created_by' => $user->id,
            'total' => 400,
            'amount_paid' => 150,
            'status' => Bill::StatusPartial,
        ]);
        Bill::factory()->create([
            'tenant_id' => $tenant->id,
            'client_id' => $client->id,
            'created_by' => $user->id,
            'total' => 300,
            'amount_paid' => 300,
            'status' => Bill::StatusPaid,
        ]);

        $summary = app(DashboardService::class)->summaryFor(Carbon::today());

        $this->assertSame('750.00', $summary['pendingPayments']);
    }

    public function test_summary_returns_null_month_change_when_no_prior_month_revenue(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);

        $summary = app(DashboardService::class)->summaryFor(Carbon::today());

        $this->assertNull($summary['monthRevenueChangePercent']);
    }

    public function test_summary_computes_positive_month_over_month_change(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);

        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $user = User::factory()->for($tenant)->create();
        $today = Carbon::today();

        Bill::factory()->create([
            'tenant_id' => $tenant->id,
            'client_id' => $client->id,
            'created_by' => $user->id,
            'total' => 200,
            'created_at' => $today->copy()->subMonthNoOverflow(),
        ]);
        Bill::factory()->create([
            'tenant_id' => $tenant->id,
            'client_id' => $client->id,
            'created_by' => $user->id,
            'total' => 300,
            'created_at' => $today,
        ]);

        $summary = app(DashboardService::class)->summaryFor($today);

        $this->assertSame(50.0, $summary['monthRevenueChangePercent']);
    }
}
