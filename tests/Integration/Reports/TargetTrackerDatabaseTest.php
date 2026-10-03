<?php

namespace Tests\Integration\Reports;

use App\Models\Bill;
use App\Models\StaffProfile;
use App\Models\StaffTarget;
use App\Services\IncentiveService;
use App\Services\TargetTrackerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\ActsAsTenant;
use Tests\Concerns\CreatesIncentiveBills;
use Tests\TestCase;

class TargetTrackerDatabaseTest extends TestCase
{
    use ActsAsTenant, CreatesIncentiveBills, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenant();
        $this->useTenantAndBranchContext();
    }

    private function staff(string $name): StaffProfile
    {
        return StaffProfile::factory()->create(['tenant_id' => $this->tenant->id, 'name' => $name]);
    }

    private function target(StaffProfile $staff, string $amount): void
    {
        StaffTarget::factory()->create(['tenant_id' => $this->tenant->id, 'staff_profile_id' => $staff->id, 'month' => '2026-06-01', 'target_amount' => $amount]);
    }

    public function test_totals_match_the_incentive_progress_screen(): void
    {
        $servicing = $this->staff('Rizwan');
        $referrer = $this->staff('Azam');
        $this->target($servicing, '3000');
        $this->target($referrer, '3000');
        $this->billWithLine('10000', $servicing, $referrer, createdAt: '2026-06-04');
        $this->billWithLine('500', $servicing, createdAt: '2026-06-20');

        $tracker = app(TargetTrackerService::class)->forMonth(Carbon::parse('2026-06-01'), Carbon::parse('2026-06-30'));
        $progress = app(IncentiveService::class)->progressForAll(Carbon::parse('2026-06-01'));

        $this->assertSame($progress->get($servicing->id)['achieved'], $tracker['staff']->get($servicing->id)['actual']);
        $this->assertSame($progress->get($referrer->id)['achieved'], $tracker['staff']->get($referrer->id)['actual']);
        $this->assertSame('10500.00', $tracker['salon']['actual']);
        $this->assertSame('6000.00', $tracker['salon']['target']);
    }

    public function test_actual_is_grouped_by_the_day_the_bill_was_raised(): void
    {
        $staff = $this->staff('Rizwan');
        $this->billWithLine('1000', $staff, createdAt: '2026-06-04');
        $this->billWithLine('250', $staff, createdAt: '2026-06-04');

        $days = app(TargetTrackerService::class)->forMonth(Carbon::parse('2026-06-01'), Carbon::parse('2026-06-30'))['salon']['days'];

        $this->assertSame('1250.00', $days[3]['actual']);
        $this->assertSame('0.00', $days[4]['actual']);
    }

    public function test_unpaid_and_void_bills_are_not_counted(): void
    {
        $staff = $this->staff('Rizwan');
        $this->billWithLine('1000', $staff, status: Bill::StatusUnpaid);
        $this->billWithLine('1000', $staff, status: Bill::StatusVoid);

        $salon = app(TargetTrackerService::class)->forMonth(Carbon::parse('2026-06-01'), Carbon::parse('2026-06-30'))['salon'];

        $this->assertSame('0.00', $salon['actual']);
    }
}
