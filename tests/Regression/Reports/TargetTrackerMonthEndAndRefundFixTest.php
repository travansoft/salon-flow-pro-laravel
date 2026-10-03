<?php

namespace Tests\Regression\Reports;

use App\Models\StaffProfile;
use App\Services\TargetTrackerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\ActsAsTenant;
use Tests\Concerns\CreatesIncentiveBills;
use Tests\TestCase;

class TargetTrackerMonthEndAndRefundFixTest extends TestCase
{
    use ActsAsTenant, CreatesIncentiveBills, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenant();
        $this->useTenantAndBranchContext();
    }

    public function test_bill_late_on_the_last_day_is_counted_in_the_last_day_and_week(): void
    {
        $staff = StaffProfile::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->billWithLine('900', $staff, createdAt: '2026-06-30 23:30:00');

        $salon = app(TargetTrackerService::class)->forMonth(Carbon::parse('2026-06-01'), Carbon::parse('2026-07-02'))['salon'];

        $this->assertSame('900.00', $salon['days'][29]['actual']);
        $this->assertSame('900.00', $salon['weeks'][4]['actual']);
    }

    public function test_refunded_bill_is_reduced_not_dropped(): void
    {
        $staff = StaffProfile::factory()->create(['tenant_id' => $this->tenant->id]);
        $bill = $this->billWithLine('1000', $staff, createdAt: '2026-06-10');
        $bill->forceFill(['total' => 1000, 'amount_refunded' => 250])->save();

        $salon = app(TargetTrackerService::class)->forMonth(Carbon::parse('2026-06-01'), Carbon::parse('2026-07-02'))['salon'];

        $this->assertSame('750.00', $salon['actual']);
    }
}
