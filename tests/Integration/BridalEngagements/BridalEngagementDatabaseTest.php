<?php

namespace Tests\Integration\BridalEngagements;

use App\Models\Bill;
use App\Models\BillPayment;
use App\Models\Branch;
use App\Models\BridalEngagement;
use App\Models\Tenant;
use App\Services\BranchContext;
use App\Services\DayBookService;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BridalEngagementDatabaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_scope_excludes_engagements_from_other_tenants(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $branchA = Branch::factory()->create(['tenant_id' => $tenantA->id]);

        BridalEngagement::factory()->create(['tenant_id' => $tenantA->id, 'branch_id' => $branchA->id]);
        BridalEngagement::factory()->create(['tenant_id' => $tenantB->id]);

        app(TenantContext::class)->set($tenantA);
        app(BranchContext::class)->set($branchA);

        $this->assertSame(1, BridalEngagement::count());
    }

    public function test_deleting_an_engagement_soft_deletes_it(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);

        $engagement = BridalEngagement::factory()->create(['tenant_id' => $tenant->id]);

        $engagement->delete();

        $this->assertSoftDeleted('bridal_engagements', ['id' => $engagement->id]);
    }

    public function test_payment_on_attached_bill_appears_in_the_day_book(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        app(BranchContext::class)->set(Branch::factory()->create(['tenant_id' => $tenant->id]));

        $engagement = BridalEngagement::factory()->create(['tenant_id' => $tenant->id]);
        $bill = Bill::factory()->paid()->create([
            'tenant_id' => $tenant->id,
            'client_id' => $engagement->client_id,
            'bridal_engagement_id' => $engagement->id,
            'total' => 7000,
        ]);
        BillPayment::factory()->create([
            'tenant_id' => $tenant->id,
            'bill_id' => $bill->id,
            'method' => 'cash',
            'amount' => 7000,
            'created_at' => Carbon::today()->setTime(10, 0),
        ]);

        $result = app(DayBookService::class)->forRange(Carbon::today(), Carbon::today());

        $this->assertSame($bill->id, $result['entries']->first()['bill_id']);
        $this->assertSame('7000.00', $result['totals']['cash']['in']);
        $this->assertTrue($engagement->bills()->whereKey($bill->id)->exists());
    }
}
