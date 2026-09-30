<?php

namespace Tests\Integration\Billing;

use App\Models\Bill;
use App\Models\BillAudit;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Tenant;
use App\Models\User;
use App\Services\BillingService;
use App\Services\BranchContext;
use App\Services\ReportService;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CancelBillDatabaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_cancelling_a_bill_persists_the_void_status(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);
        $bill = Bill::factory()->create(['tenant_id' => $tenant->id]);
        $user = User::factory()->for($tenant)->create();

        app(BillingService::class)->cancel($bill, $user->id);

        $this->assertDatabaseHas('bills', ['id' => $bill->id, 'status' => Bill::StatusVoid]);
        $this->assertDatabaseHas('bill_audits', [
            'bill_id' => $bill->id,
            'action' => BillAudit::ActionCancelled,
            'changed_by' => $user->id,
        ]);
    }

    public function test_cancelled_bills_are_excluded_from_report_revenue_totals(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);
        $today = Carbon::today();

        $user = User::factory()->for($tenant)->create();
        Bill::factory()->create(['tenant_id' => $tenant->id, 'total' => 500, 'created_at' => $today]);
        $cancelledBill = Bill::factory()->create(['tenant_id' => $tenant->id, 'total' => 2000, 'created_at' => $today]);
        app(BillingService::class)->cancel($cancelledBill, $user->id);

        $report = app(ReportService::class)->reportFor($today, $today);

        $this->assertSame('500.00', $report['totalRevenue']);
        $this->assertSame(1, $report['billCount']);
    }

    public function test_editing_a_bill_persists_the_new_client_and_note(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);
        $bill = Bill::factory()->create(['tenant_id' => $tenant->id]);
        $newClient = Client::factory()->create(['tenant_id' => $tenant->id, 'gst_number' => '32AAAAA0000A1Z5']);
        $user = User::factory()->for($tenant)->create();

        app(BillingService::class)->editBill($bill, $newClient->id, 'GST invoice requested', null, $user->id);

        $this->assertDatabaseHas('bills', [
            'id' => $bill->id,
            'client_id' => $newClient->id,
            'notes' => 'GST invoice requested',
        ]);
    }
}
