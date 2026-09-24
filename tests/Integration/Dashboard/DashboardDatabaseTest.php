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

        Bill::factory()->create([
            'tenant_id' => $tenantA->id,
            'client_id' => $clientA->id,
            'created_by' => $userA->id,
            'total' => 500,
            'created_at' => now(),
        ]);
        Bill::factory()->create([
            'tenant_id' => $tenantB->id,
            'client_id' => $clientB->id,
            'created_by' => $userB->id,
            'total' => 9000,
            'created_at' => now(),
        ]);

        app(TenantContext::class)->set($tenantA);
        $branchA = Branch::factory()->create(['tenant_id' => $tenantA->id]);
        app(BranchContext::class)->set($branchA);
        $summary = app(DashboardService::class)->summaryFor(Carbon::today());

        $this->assertSame('500.00', $summary['todaysRevenue']);
    }
}
