<?php

namespace App\Services;

use App\Models\BillLineItem;
use App\Models\StaffProfile;
use App\Repositories\Contracts\BillLineItemRepositoryInterface;
use App\Repositories\Contracts\StaffProfileRepositoryInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class TargetTrackerService
{
    public const Headings = [
        'Level',
        'Staff',
        'Period type',
        'Period',
        'Expected',
        'Actual',
        'Cumulative expected',
        'Cumulative actual',
        'Variance',
        'Status',
    ];

    public const OnTrackThresholdPercent = '90';

    public const StatusStyles = [
        'ahead' => ['label' => 'Ahead', 'color' => '#1E7B4F'],
        'on-track' => ['label' => 'On track', 'color' => '#B7791F'],
        'behind' => ['label' => 'Behind', 'color' => '#C0392B'],
        'no-target' => ['label' => 'No target', 'color' => '#94A19D'],
    ];

    public function __construct(
        private IncentiveService $incentiveService,
        private BillLineItemRepositoryInterface $billLineItemRepository,
        private StaffProfileRepositoryInterface $staffProfileRepository,
    ) {}

    /**
     * Expected versus actual progress of the salon and of every active staff member.
     *
     * @return array{
     *     salon: array<string, mixed>,
     *     staff: Collection<int, array<string, mixed>>,
     * }
     */
    public function forMonth(Carbon $month, ?Carbon $today = null): array
    {
        $month = $month->copy()->startOfMonth();
        $today = ($today ?? Carbon::today())->copy()->startOfDay();

        $targets = $this->incentiveService->targetsForMonth($month);
        $dailyCredits = $this->dailyCredits($month);

        $salonTarget = $targets->reduce(fn (string $carry, string $amount): string => bcadd($carry, $amount, 2), '0.00');
        $salonDaily = $dailyCredits->reduce(function (array $carry, array $days): array {
            foreach ($days as $date => $amount) {
                $carry[$date] = bcadd($carry[$date] ?? '0.00', $amount, 2);
            }

            return $carry;
        }, []);

        $staff = $this->staffProfileRepository->getActive()->mapWithKeys(fn (StaffProfile $profile): array => [
            $profile->id => [
                'staff' => $profile,
                ...$this->progress($month, $today, $targets->get($profile->id), $dailyCredits->get($profile->id, [])),
            ],
        ]);

        return [
            'salon' => $this->progress($month, $today, $salonTarget, $salonDaily),
            'staff' => $staff,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $salon
     * @param  Collection<int, array<string, mixed>>  $staffRows
     * @return array<int, array<int, string|int|float|null>>
     */
    public function exportRows(?array $salon, Collection $staffRows): array
    {
        $rows = [];

        if ($salon !== null) {
            $rows = [...$rows, ...$this->exportRowsFor('Salon', 'All staff', $salon)];
        }

        foreach ($staffRows as $row) {
            $rows = [...$rows, ...$this->exportRowsFor('Staff', $row['staff']->name, $row)];
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $progress
     * @return array<int, array<int, string|int|float|null>>
     */
    private function exportRowsFor(string $level, string $staffName, array $progress): array
    {
        $rows = [];

        foreach ($progress['days'] as $day) {
            $rows[] = [
                $level, $staffName, 'Day', $day['date']->format('d M Y'),
                $day['expected'], $day['actual'], $day['cumulativeExpected'], $day['cumulativeActual'],
                $day['variance'], $day['status'],
            ];
        }

        foreach ($progress['weeks'] as $week) {
            $rows[] = [
                $level, $staffName, 'Week', "{$week['from']->format('d M')} - {$week['to']->format('d M Y')}",
                $week['expected'], $week['actual'], null, null,
                $week['variance'], $week['status'],
            ];
        }

        return $rows;
    }

    /**
     * Credit per staff member per day, using the same split as the incentive progress screen.
     *
     * @return Collection<int, array<string, string>>
     */
    private function dailyCredits(Carbon $month): Collection
    {
        $setting = $this->incentiveService->getSettings();
        $lineItems = $this->billLineItemRepository->getPaidBetween($month->copy()->startOfMonth(), $month->copy()->endOfMonth());
        $credits = [];

        /** @var BillLineItem $lineItem */
        foreach ($lineItems as $lineItem) {
            if ($lineItem->staff_profile_id === null) {
                continue;
            }

            $date = $lineItem->bill->created_at->toDateString();
            $split = $this->incentiveService->splitLine($lineItem, $setting);

            $credits[$lineItem->staff_profile_id][$date] = bcadd($credits[$lineItem->staff_profile_id][$date] ?? '0.00', $split['servicingAmount'], 2);

            if ($split['referrerAmount'] !== null) {
                $credits[$lineItem->referred_by_staff_profile_id][$date] = bcadd($credits[$lineItem->referred_by_staff_profile_id][$date] ?? '0.00', $split['referrerAmount'], 2);
            }
        }

        return collect($credits);
    }

    /**
     * @param  array<string, string>  $dailyActual  credit keyed by Y-m-d
     * @return array<string, mixed>
     */
    private function progress(Carbon $month, Carbon $today, ?string $target, array $dailyActual): array
    {
        $target ??= '0.00';
        $hasTarget = bccomp($target, '0', 2) > 0;
        $daysInMonth = $month->daysInMonth;
        $elapsed = $this->elapsedDays($month, $today);

        $cumulativeExpected = [0 => '0.00'];
        $days = [];
        $cumulativeActual = '0.00';

        for ($day = 1; $day <= $daysInMonth; $day++) {
            $date = $month->copy()->day($day);
            $cumulativeExpected[$day] = bcdiv(bcmul($target, (string) $day, 6), (string) $daysInMonth, 2);
            $expected = bcsub($cumulativeExpected[$day], $cumulativeExpected[$day - 1], 2);
            $actual = $dailyActual[$date->toDateString()] ?? '0.00';
            $isElapsed = $day <= $elapsed;

            if ($isElapsed) {
                $cumulativeActual = bcadd($cumulativeActual, $actual, 2);
            }

            $days[] = [
                'date' => $date,
                'expected' => $expected,
                'cumulativeExpected' => $cumulativeExpected[$day],
                'actual' => $isElapsed ? $actual : null,
                'cumulativeActual' => $isElapsed ? $cumulativeActual : null,
                'variance' => $isElapsed ? bcsub($cumulativeActual, $cumulativeExpected[$day], 2) : null,
                'status' => $isElapsed ? $this->status($hasTarget, $cumulativeActual, $cumulativeExpected[$day]) : null,
                'isToday' => $date->equalTo($today),
            ];
        }

        $actualToDate = $cumulativeActual;
        $expectedToDate = $cumulativeExpected[$elapsed] ?? '0.00';

        return [
            'target' => $target,
            'hasTarget' => $hasTarget,
            'actual' => $actualToDate,
            'expectedToDate' => $expectedToDate,
            'variance' => bcsub($actualToDate, $expectedToDate, 2),
            'achievementPercent' => $hasTarget ? bcdiv(bcmul($actualToDate, '100', 4), $target, 2) : null,
            'pacePercent' => bccomp($expectedToDate, '0', 2) > 0 ? bcdiv(bcmul($actualToDate, '100', 4), $expectedToDate, 2) : null,
            'status' => $elapsed > 0 ? $this->status($hasTarget, $actualToDate, $expectedToDate) : null,
            'projected' => $elapsed > 0 ? bcdiv(bcmul($actualToDate, (string) $daysInMonth, 4), (string) $elapsed, 2) : null,
            'days' => $days,
            'weeks' => $this->weeks($month, $elapsed, $hasTarget, $cumulativeExpected, $days),
        ];
    }

    /**
     * Calendar weeks (Monday to Sunday) clipped to the month.
     *
     * @param  array<int, string>  $cumulativeExpected
     * @param  array<int, array<string, mixed>>  $days
     * @return array<int, array<string, mixed>>
     */
    private function weeks(Carbon $month, int $elapsed, bool $hasTarget, array $cumulativeExpected, array $days): array
    {
        $weeks = [];
        $startDay = 1;
        $number = 1;

        while ($startDay <= $month->daysInMonth) {
            $startDate = $month->copy()->day($startDay);
            $endDay = min($month->daysInMonth, $startDay + (7 - $startDate->dayOfWeekIso));
            $elapsedInWeek = min($endDay, $elapsed);

            $expected = bcsub($cumulativeExpected[$endDay], $cumulativeExpected[$startDay - 1], 2);
            $expectedToDate = $elapsedInWeek >= $startDay ? bcsub($cumulativeExpected[$elapsedInWeek], $cumulativeExpected[$startDay - 1], 2) : null;
            $actual = null;

            if ($expectedToDate !== null) {
                $actual = '0.00';

                for ($day = $startDay; $day <= $elapsedInWeek; $day++) {
                    $actual = bcadd($actual, $days[$day - 1]['actual'], 2);
                }
            }

            $weeks[] = [
                'number' => $number,
                'from' => $startDate,
                'to' => $month->copy()->day($endDay),
                'expected' => $expected,
                'actual' => $actual,
                'variance' => $actual === null ? null : bcsub($actual, $expectedToDate, 2),
                'achievementPercent' => $actual !== null && bccomp($expected, '0', 2) > 0 ? bcdiv(bcmul($actual, '100', 4), $expected, 2) : null,
                'status' => $actual === null ? null : $this->status($hasTarget, $actual, $expectedToDate),
            ];

            $startDay = $endDay + 1;
            $number++;
        }

        return $weeks;
    }

    private function elapsedDays(Carbon $month, Carbon $today): int
    {
        if ($today->lt($month)) {
            return 0;
        }

        if ($today->gt($month->copy()->endOfMonth())) {
            return $month->daysInMonth;
        }

        return $today->day;
    }

    private function status(bool $hasTarget, string $actual, string $expected): string
    {
        if (! $hasTarget) {
            return 'no-target';
        }

        if (bccomp($actual, $expected, 2) >= 0) {
            return 'ahead';
        }

        $onTrackFloor = bcdiv(bcmul($expected, self::OnTrackThresholdPercent, 4), '100', 2);

        if (bccomp($actual, $onTrackFloor, 2) >= 0) {
            return 'on-track';
        }

        return 'behind';
    }
}
