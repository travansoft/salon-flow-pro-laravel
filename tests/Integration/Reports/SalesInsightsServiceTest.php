<?php

namespace Tests\Integration\Reports;

use App\Models\Bill;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Expense;
use App\Models\Tenant;
use App\Services\BranchContext;
use App\Services\SalesInsightsService;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SalesInsightsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_insights_report_footfall_averages_net_and_period_change(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        app(BranchContext::class)->set(Branch::factory()->create(['tenant_id' => $tenant->id]));

        $today = Carbon::today();
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        Bill::factory()->paid()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id, 'total' => 600, 'created_at' => $today]);
        Bill::factory()->paid()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id, 'total' => 400, 'created_at' => $today]);
        Bill::factory()->paid()->create(['tenant_id' => $tenant->id, 'total' => 500, 'created_at' => $today->copy()->subDay()]);

        Expense::factory()->create([
            'tenant_id' => $tenant->id,
            'amount' => 250,
            'expense_date' => $today->format('Y-m-d'),
        ]);

        $insights = app(SalesInsightsService::class)->forRange($today, $today);

        $this->assertSame(1, $insights['uniqueClients']);
        $this->assertSame('500.00', $insights['avgBillValue']);
        $this->assertSame('250.00', $insights['expenseTotal']);
        $this->assertSame('750.00', $insights['netAmount']);
        $this->assertSame(['percent' => '100.0', 'direction' => 'up'], $insights['revenueChange']);
        $this->assertSame(1, $insights['dailyRevenue'][0]['footfall']);
    }
}
