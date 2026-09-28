<?php

namespace Tests\Integration\Dashboard;

use App\Models\Bill;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Tenant;
use App\Models\User;
use App\Services\BranchContext;
use App\Services\DashboardService;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DashboardDatabaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_summary_only_reflects_bills_for_the_active_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $clientA = Client::factory()->create(['tenant_id' => $tenantA->id]);
        $userA = User::factory()->for($tenantA)->create();
        $clientB = Client::factory()->create(['tenant_id' => $tenantB->id]);
        $userB = User::factory()->for($tenantB)->create();

        $branchA = Branch::factory()->create(['tenant_id' => $tenantA->id]);
        $branchB = Branch::factory()->create(['tenant_id' => $tenantB->id]);

        app(TenantContext::class)->set($tenantA);
        app(BranchContext::class)->set($branchA);
        Bill::factory()->create([
            'tenant_id' => $tenantA->id,
            'branch_id' => $branchA->id,
            'client_id' => $clientA->id,
            'created_by' => $userA->id,
            'total' => 500,
            'created_at' => now(),
        ]);

        app(TenantContext::class)->set($tenantB);
        app(BranchContext::class)->set($branchB);
        Bill::factory()->create([
            'tenant_id' => $tenantB->id,
            'branch_id' => $branchB->id,
            'client_id' => $clientB->id,
            'created_by' => $userB->id,
            'total' => 9000,
            'created_at' => now(),
        ]);

        app(TenantContext::class)->set($tenantA);
        app(BranchContext::class)->set($branchA);
        $summary = app(DashboardService::class)->summaryFor(Carbon::today());

        $this->assertSame('500.00', $summary['todaysRevenue']);
    }

    public function test_dashboard_summary_only_reflects_bills_for_the_current_branch_not_other_branches_in_the_same_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);

        $branchA = Branch::factory()->create(['tenant_id' => $tenant->id]);
        $branchB = Branch::factory()->create(['tenant_id' => $tenant->id]);

        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $user = User::factory()->for($tenant)->create();

        app(BranchContext::class)->set($branchA);
        Bill::factory()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branchA->id,
            'client_id' => $client->id,
            'created_by' => $user->id,
            'total' => 500,
            'created_at' => now(),
        ]);

        app(BranchContext::class)->set($branchB);
        Bill::factory()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branchB->id,
            'client_id' => $client->id,
            'created_by' => $user->id,
            'total' => 9000,
            'created_at' => now(),
        ]);

        app(BranchContext::class)->set($branchA);
        $summaryForBranchA = app(DashboardService::class)->summaryFor(Carbon::today());

        app(BranchContext::class)->set($branchB);
        $summaryForBranchB = app(DashboardService::class)->summaryFor(Carbon::today());

        $this->assertSame('500.00', $summaryForBranchA['todaysRevenue']);
        $this->assertSame('9000.00', $summaryForBranchB['todaysRevenue']);
    }
}
