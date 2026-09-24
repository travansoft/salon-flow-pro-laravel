<?php

namespace Tests\Unit\Commission;

use App\Models\Bill;
use App\Models\BillLineItem;
use App\Models\Branch;
use App\Models\CommissionRate;
use App\Models\ServiceCategory;
use App\Models\StaffIncentive;
use App\Models\StaffProfile;
use App\Models\Tenant;
use App\Repositories\Contracts\CommissionRateRepositoryInterface;
use App\Repositories\Contracts\StaffIncentiveRepositoryInterface;
use App\Services\BranchContext;
use App\Services\CommissionService;
use App\Services\TenantContext;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Mockery;
use Tests\TestCase;

class CommissionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolve_rate_for_prefers_exact_staff_and_category_over_all_other_tiers(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);
        $staff = StaffProfile::factory()->create(['tenant_id' => $tenant->id]);
        $category = ServiceCategory::factory()->create(['tenant_id' => $tenant->id]);

        CommissionRate::factory()->create([
            'tenant_id' => $tenant->id,
            'staff_profile_id' => null,
            'service_category_id' => null,
            'rate_percent' => 10,
            'effective_from' => '2026-01-01',
        ]);
        CommissionRate::factory()->create([
            'tenant_id' => $tenant->id,
            'staff_profile_id' => null,
            'service_category_id' => $category->id,
            'rate_percent' => 12,
            'effective_from' => '2026-01-01',
        ]);
        CommissionRate::factory()->create([
            'tenant_id' => $tenant->id,
            'staff_profile_id' => $staff->id,
            'service_category_id' => null,
            'rate_percent' => 18,
            'effective_from' => '2026-01-01',
        ]);
        CommissionRate::factory()->create([
            'tenant_id' => $tenant->id,
            'staff_profile_id' => $staff->id,
            'service_category_id' => $category->id,
            'rate_percent' => 20,
            'effective_from' => '2026-01-01',
        ]);

        $service = $this->serviceWithMockedIncentives();

        $rate = $service->resolveRateFor($staff, $category->id, Carbon::parse('2026-06-01'));

        $this->assertSame('20.00', $rate);
    }

    public function test_resolve_rate_for_falls_back_to_staff_wide_rate_when_no_category_specific_rate_exists(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);
        $staff = StaffProfile::factory()->create(['tenant_id' => $tenant->id]);
        $category = ServiceCategory::factory()->create(['tenant_id' => $tenant->id]);

        CommissionRate::factory()->create([
            'tenant_id' => $tenant->id,
            'staff_profile_id' => null,
            'service_category_id' => null,
            'rate_percent' => 10,
            'effective_from' => '2026-01-01',
        ]);
        CommissionRate::factory()->create([
            'tenant_id' => $tenant->id,
            'staff_profile_id' => $staff->id,
            'service_category_id' => null,
            'rate_percent' => 18,
            'effective_from' => '2026-01-01',
        ]);

        $service = $this->serviceWithMockedIncentives();

        $rate = $service->resolveRateFor($staff, $category->id, Carbon::parse('2026-06-01'));

        $this->assertSame('18.00', $rate);
    }

    public function test_resolve_rate_for_falls_back_to_category_wide_rate_when_no_staff_specific_rate_exists(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);
        $staff = StaffProfile::factory()->create(['tenant_id' => $tenant->id]);
        $category = ServiceCategory::factory()->create(['tenant_id' => $tenant->id]);

        CommissionRate::factory()->create([
            'tenant_id' => $tenant->id,
            'staff_profile_id' => null,
            'service_category_id' => null,
            'rate_percent' => 10,
            'effective_from' => '2026-01-01',
        ]);
        CommissionRate::factory()->create([
            'tenant_id' => $tenant->id,
            'staff_profile_id' => null,
            'service_category_id' => $category->id,
            'rate_percent' => 12,
            'effective_from' => '2026-01-01',
        ]);

        $service = $this->serviceWithMockedIncentives();

        $rate = $service->resolveRateFor($staff, $category->id, Carbon::parse('2026-06-01'));

        $this->assertSame('12.00', $rate);
    }

    public function test_resolve_rate_for_returns_zero_when_no_rate_matches(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);
        $staff = StaffProfile::factory()->create(['tenant_id' => $tenant->id]);

        $service = $this->serviceWithMockedIncentives();

        $rate = $service->resolveRateFor($staff, null, Carbon::parse('2026-06-01'));

        $this->assertSame('0.00', $rate);
    }

    public function test_resolve_rate_for_ignores_rates_not_yet_effective(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);
        $staff = StaffProfile::factory()->create(['tenant_id' => $tenant->id]);

        CommissionRate::factory()->create([
            'tenant_id' => $tenant->id,
            'staff_profile_id' => $staff->id,
            'service_category_id' => null,
            'rate_percent' => 10,
            'effective_from' => '2026-01-01',
        ]);
        CommissionRate::factory()->create([
            'tenant_id' => $tenant->id,
            'staff_profile_id' => $staff->id,
            'service_category_id' => null,
            'rate_percent' => 25,
            'effective_from' => '2026-12-01',
        ]);

        $service = $this->serviceWithMockedIncentives();

        $rate = $service->resolveRateFor($staff, null, Carbon::parse('2026-06-01'));

        $this->assertSame('10.00', $rate);
    }

    public function test_resolve_rate_for_uses_latest_effective_rate_within_the_same_tier(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);
        $staff = StaffProfile::factory()->create(['tenant_id' => $tenant->id]);

        CommissionRate::factory()->create([
            'tenant_id' => $tenant->id,
            'staff_profile_id' => $staff->id,
            'service_category_id' => null,
            'rate_percent' => 10,
            'effective_from' => '2026-01-01',
        ]);
        CommissionRate::factory()->create([
            'tenant_id' => $tenant->id,
            'staff_profile_id' => $staff->id,
            'service_category_id' => null,
            'rate_percent' => 15,
            'effective_from' => '2026-03-01',
        ]);

        $service = $this->serviceWithMockedIncentives();

        $rate = $service->resolveRateFor($staff, null, Carbon::parse('2026-06-01'));

        $this->assertSame('15.00', $rate);
    }

    public function test_earnings_for_excludes_unpaid_bills_and_sums_only_paid_ones(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);
        $staff = StaffProfile::factory()->create(['tenant_id' => $tenant->id]);

        CommissionRate::factory()->create([
            'tenant_id' => $tenant->id,
            'staff_profile_id' => null,
            'service_category_id' => null,
            'rate_percent' => 10,
            'effective_from' => '2026-01-01',
        ]);

        $paidBill = Bill::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => Bill::StatusPaid,
            'created_at' => '2026-06-10',
        ]);
        BillLineItem::factory()->create([
            'tenant_id' => $tenant->id,
            'bill_id' => $paidBill->id,
            'staff_profile_id' => $staff->id,
            'line_total' => 1000,
        ]);

        $unpaidBill = Bill::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => Bill::StatusUnpaid,
            'created_at' => '2026-06-11',
        ]);
        BillLineItem::factory()->create([
            'tenant_id' => $tenant->id,
            'bill_id' => $unpaidBill->id,
            'staff_profile_id' => $staff->id,
            'line_total' => 5000,
        ]);

        $staffIncentiveRepository = Mockery::mock(StaffIncentiveRepositoryInterface::class);
        $staffIncentiveRepository->shouldReceive('getForStaffBetweenDates')
            ->once()
            ->andReturn(new Collection);

        $service = new CommissionService(
            app(CommissionRateRepositoryInterface::class),
            $staffIncentiveRepository,
        );

        $result = $service->earningsFor($staff, Carbon::parse('2026-06-01'), Carbon::parse('2026-06-30'));

        $this->assertSame('100.00', $result['commissionEarned']);
        $this->assertSame('0.00', $result['incentivesEarned']);
        $this->assertSame('100.00', $result['totalEarned']);
        $this->assertSame(1, $result['lineItemCount']);
    }

    public function test_earnings_for_adds_incentives_awarded_in_the_period_to_the_total(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);
        $staff = StaffProfile::factory()->create(['tenant_id' => $tenant->id]);

        $staffIncentiveRepository = Mockery::mock(StaffIncentiveRepositoryInterface::class);
        $staffIncentiveRepository->shouldReceive('getForStaffBetweenDates')
            ->once()
            ->andReturn(new Collection([
                new StaffIncentive(['amount' => '500.00']),
                new StaffIncentive(['amount' => '250.00']),
            ]));

        $service = new CommissionService(
            app(CommissionRateRepositoryInterface::class),
            $staffIncentiveRepository,
        );

        $result = $service->earningsFor($staff, Carbon::parse('2026-06-01'), Carbon::parse('2026-06-30'));

        $this->assertSame('0.00', $result['commissionEarned']);
        $this->assertSame('750.00', $result['incentivesEarned']);
        $this->assertSame('750.00', $result['totalEarned']);
    }

    private function serviceWithMockedIncentives(): CommissionService
    {
        $staffIncentiveRepository = Mockery::mock(StaffIncentiveRepositoryInterface::class);

        return new CommissionService(
            app(CommissionRateRepositoryInterface::class),
            $staffIncentiveRepository,
        );
    }
}
