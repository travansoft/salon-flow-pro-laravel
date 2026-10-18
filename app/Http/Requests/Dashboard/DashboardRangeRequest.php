<?php

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class DashboardRangeRequest extends FormRequest
{
    public const PeriodToday = 'today';

    public const PeriodWeek = 'week';

    public const PeriodMonth = 'month';

    public const PeriodCustom = 'custom';

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'period' => ['nullable', Rule::in([self::PeriodToday, self::PeriodWeek, self::PeriodMonth, self::PeriodCustom])],
            'from' => ['nullable', 'required_if:period,custom', 'date'],
            'to' => ['nullable', 'required_if:period,custom', 'date', 'after_or_equal:from'],
        ];
    }

    public function period(): string
    {
        $period = $this->validated('period') ?? self::PeriodToday;

        if ($period === self::PeriodCustom && ! $this->validated('from')) {
            return self::PeriodToday;
        }

        return $period;
    }

    public function periodLabel(): string
    {
        [$from, $to] = $this->range();

        return match ($this->period()) {
            self::PeriodWeek => 'this week',
            self::PeriodMonth => 'this month',
            self::PeriodCustom => "from {$from->format('d M Y')} to {$to->format('d M Y')}",
            default => 'today',
        };
    }

    public function periodTitle(): string
    {
        return match ($this->period()) {
            self::PeriodWeek => 'Weekly',
            self::PeriodMonth => 'Monthly',
            self::PeriodCustom => 'Period',
            default => "Today's",
        };
    }

    /** @return array{0: Carbon, 1: Carbon} */
    public function range(): array
    {
        $today = Carbon::today();

        return match ($this->period()) {
            self::PeriodWeek => [$today->copy()->startOfWeek(), $today],
            self::PeriodMonth => [$today->copy()->startOfMonth(), $today],
            self::PeriodCustom => [
                Carbon::parse($this->validated('from'))->startOfDay(),
                Carbon::parse($this->validated('to'))->startOfDay(),
            ],
            default => [$today->copy(), $today->copy()],
        };
    }
}
