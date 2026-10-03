<?php

namespace Tests\Unit\Reports;

use App\Models\Bill;
use App\Models\BillLineItem;
use App\Models\IncentiveSetting;
use App\Models\StaffProfile;
use App\Repositories\Contracts\BillLineItemRepositoryInterface;
use App\Repositories\Contracts\StaffProfileRepositoryInterface;
use App\Services\IncentiveService;
use App\Services\TargetTrackerService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Mockery;
use Tests\TestCase;

class TargetTrackerServiceTest extends TestCase
{
    private function staff(int $id): StaffProfile
    {
        return (new StaffProfile(['name' => "Staff {$id}"]))->forceFill(['id' => $id]);
    }

    private function line(int $staffId, string $date, ?int $referrerId = null): BillLineItem
    {
        $bill = (new Bill)->forceFill(['created_at' => Carbon::parse($date)]);

        return (new BillLineItem)
            ->forceFill(['staff_profile_id' => $staffId, 'referred_by_staff_profile_id' => $referrerId])
            ->setRelation('bill', $bill);
    }

    /**
     * @param  array<int, string>  $targets
     * @param  array<int, array{0: BillLineItem, 1: string, 2: ?string}>  $lines  line, servicing credit, referrer credit
     */
    private function service(array $targets, array $lines, int ...$staffIds): TargetTrackerService
    {
        $incentives = Mockery::mock(IncentiveService::class);
        $incentives->shouldReceive('targetsForMonth')->andReturn(collect($targets));
        $incentives->shouldReceive('getSettings')->andReturn(new IncentiveSetting);
        $incentives->shouldReceive('splitLine')->andReturnUsing(function (BillLineItem $line) use ($lines): array {
            foreach ($lines as [$candidate, $servicing, $referrer]) {
                if ($candidate === $line) {
                    return ['basis' => $servicing, 'servicingAmount' => $servicing, 'servicingPercent' => '100', 'referrerAmount' => $referrer, 'referringPercent' => null];
                }
            }

            return [];
        });

        $lineRepository = Mockery::mock(BillLineItemRepositoryInterface::class);
        $lineRepository->shouldReceive('getPaidBetween')->andReturn(new EloquentCollection(array_column($lines, 0)));

        $staffRepository = Mockery::mock(StaffProfileRepositoryInterface::class);
        $staffRepository->shouldReceive('getActive')->andReturn(new EloquentCollection(array_map(fn (int $id) => $this->staff($id), $staffIds)));

        return new TargetTrackerService($incentives, $lineRepository, $staffRepository);
    }

    public function test_expected_accumulates_evenly_and_reaches_the_target_on_the_last_day(): void
    {
        $service = $this->service([1 => '3000.00'], [], 1);

        $result = $service->forMonth(Carbon::parse('2026-06-01'), Carbon::parse('2026-07-05'));

        $this->assertCount(30, $result['staff']->get(1)['days']);
        $this->assertSame('100.00', $result['staff']->get(1)['days'][0]['expected']);
        $this->assertSame('3000.00', $result['staff']->get(1)['days'][29]['cumulativeExpected']);
        $this->assertSame('3000.00', $result['salon']['target']);
    }

    public function test_weeks_are_monday_to_sunday_clipped_to_the_month(): void
    {
        $service = $this->service([1 => '3000.00'], [], 1);

        $weeks = $service->forMonth(Carbon::parse('2026-06-01'), Carbon::parse('2026-07-05'))['salon']['weeks'];

        $this->assertCount(5, $weeks);
        $this->assertSame('2026-06-07', $weeks[0]['to']->toDateString());
        $this->assertSame('2026-06-30', $weeks[4]['to']->toDateString());
        $this->assertSame('200.00', $weeks[4]['expected']);
        $this->assertSame('3000.00', array_reduce($weeks, fn (string $sum, array $week) => bcadd($sum, $week['expected'], 2), '0.00'));
    }

    public function test_actual_variance_status_and_projection_use_days_elapsed(): void
    {
        $lines = [[$this->line(1, '2026-06-01'), '1200.00', null], [$this->line(1, '2026-06-02'), '300.00', null]];
        $service = $this->service([1 => '3000.00'], $lines, 1);

        $staff = $service->forMonth(Carbon::parse('2026-06-01'), Carbon::parse('2026-06-10'))['staff']->get(1);

        $this->assertSame('1500.00', $staff['actual']);
        $this->assertSame('1000.00', $staff['expectedToDate']);
        $this->assertSame('500.00', $staff['variance']);
        $this->assertSame('ahead', $staff['status']);
        $this->assertSame('4500.00', $staff['projected']);
        $this->assertNull($staff['days'][10]['actual']);
    }

    public function test_behind_when_actual_is_below_ninety_percent_of_expected(): void
    {
        $service = $this->service([1 => '3000.00'], [[$this->line(1, '2026-06-01'), '800.00', null]], 1);

        $staff = $service->forMonth(Carbon::parse('2026-06-01'), Carbon::parse('2026-06-10'))['staff']->get(1);

        $this->assertSame('behind', $staff['status']);
    }

    public function test_referral_credit_counts_towards_the_referrer_and_the_salon(): void
    {
        $lines = [[$this->line(1, '2026-06-03', 2), '700.00', '300.00']];
        $service = $this->service([1 => '3000.00', 2 => '3000.00'], $lines, 1, 2);

        $result = $service->forMonth(Carbon::parse('2026-06-01'), Carbon::parse('2026-06-10'));

        $this->assertSame('700.00', $result['staff']->get(1)['actual']);
        $this->assertSame('300.00', $result['staff']->get(2)['actual']);
        $this->assertSame('1000.00', $result['salon']['actual']);
        $this->assertSame('6000.00', $result['salon']['target']);
    }

    public function test_staff_without_target_has_no_target_status(): void
    {
        $service = $this->service([], [], 1);

        $staff = $service->forMonth(Carbon::parse('2026-06-01'), Carbon::parse('2026-06-10'))['staff']->get(1);

        $this->assertFalse($staff['hasTarget']);
        $this->assertNull($staff['achievementPercent']);
        $this->assertSame('no-target', $staff['status']);
    }

    public function test_past_month_is_fully_elapsed_and_future_month_has_no_actuals(): void
    {
        $service = $this->service([1 => '3000.00'], [], 1);

        $past = $service->forMonth(Carbon::parse('2026-06-01'), Carbon::parse('2026-08-01'))['salon'];
        $future = $service->forMonth(Carbon::parse('2026-09-01'), Carbon::parse('2026-08-01'))['salon'];

        $this->assertSame('3000.00', $past['expectedToDate']);
        $this->assertNotNull($past['days'][29]['actual']);
        $this->assertSame('0.00', $future['expectedToDate']);
        $this->assertNull($future['days'][0]['actual']);
        $this->assertNull($future['projected']);
    }
}
