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

class DashboardRangeDatabaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_range_summary_includes_bills_on_both_boundary_days_and_skips_voids(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        app(BranchContext::class)->set(Branch::factory()->create(['tenant_id' => $tenant->id]));
        $user = User::factory()->for($tenant)->create();

        $make = fn (string $at, float $total, string $status = Bill::StatusPaid) => Bill::factory()->create([
            'tenant_id' => $tenant->id,
            'client_id' => Client::factory()->create(['tenant_id' => $tenant->id])->id,
            'created_by' => $user->id,
            'total' => $total,
            'status' => $status,
            'created_at' => Carbon::parse($at),
        ]);

        $make('2026-04-01 00:05:00', 100);
        $make('2026-04-03 23:55:00', 200);
        $make('2026-04-02 10:00:00', 999, Bill::StatusVoid);
        $make('2026-04-04 00:05:00', 999);

        $summary = app(DashboardService::class)->summaryFor(Carbon::parse('2026-04-01'), Carbon::parse('2026-04-03'));

        $this->assertSame('300.00', $summary['revenue']);
        $this->assertSame(2, $summary['customerCount']);
    }
}
