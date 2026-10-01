<?php

namespace App\Services;

use App\Models\Bill;
use App\Models\Expense;
use App\Repositories\Contracts\BillRepositoryInterface;
use App\Repositories\Contracts\ExpenseRepositoryInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class SalesInsightsService
{
    private const MaxTrendDays = 31;

    public function __construct(
        private BillRepositoryInterface $billRepository,
        private ExpenseRepositoryInterface $expenseRepository,
    ) {}

    /** @return array<string, mixed> */
    public function forRange(Carbon $from, Carbon $to): array
    {
        $bills = $this->billRepository->forDateRange($from, $to);
        $revenue = $this->sum($bills, 'total');
        $refunds = $this->refundTotal($from, $to);
        $expenses = $this->expenseRepository->getBetweenDates($from, $to);
        $expenseTotal = $this->sumExpenses($expenses);

        return [
            'uniqueClients' => $bills->pluck('client_id')->unique()->count(),
            'avgBillValue' => $bills->isEmpty() ? '0.00' : bcdiv($revenue, (string) $bills->count(), 2),
            'gstCollected' => $this->sum($bills, 'tax_amount'),
            'discountsGiven' => $this->sum($bills, 'discount_amount'),
            'refundTotal' => $refunds,
            'expenseTotal' => $expenseTotal,
            'netAmount' => bcsub(bcsub($revenue, $refunds, 2), $expenseTotal, 2),
            'expensesByCategory' => $this->expensesByCategory($expenses),
            'revenueChange' => $this->revenueChange($from, $to, $revenue),
            'dailyRevenue' => $this->dailyRevenue($from, $to, $bills),
            'trendTruncated' => $from->diffInDays($to) + 1 > self::MaxTrendDays,
        ];
    }

    /** @param Collection<int, Bill> $bills */
    private function sum(Collection $bills, string $column): string
    {
        return $bills->reduce(fn (string $carry, Bill $bill): string => bcadd($carry, (string) $bill->{$column}, 2), '0.00');
    }

    /** @param Collection<int, Expense> $expenses */
    private function sumExpenses(Collection $expenses): string
    {
        return $expenses->reduce(fn (string $carry, Expense $expense): string => bcadd($carry, (string) $expense->amount, 2), '0.00');
    }

    private function refundTotal(Carbon $from, Carbon $to): string
    {
        return $this->billRepository->refundsBetween($from, $to)
            ->reduce(fn (string $carry, $refund): string => bcadd($carry, (string) $refund->amount, 2), '0.00');
    }

    /**
     * @param  Collection<int, Expense>  $expenses
     * @return array<int, array{name: string, amount: string}>
     */
    private function expensesByCategory(Collection $expenses): array
    {
        $totals = [];

        foreach ($expenses as $expense) {
            $name = $expense->category?->name ?? 'Uncategorised';
            $totals[$name] = bcadd($totals[$name] ?? '0.00', (string) $expense->amount, 2);
        }

        arsort($totals);

        return collect($totals)
            ->take(5)
            ->map(fn (string $amount, string $name): array => ['name' => $name, 'amount' => $amount])
            ->values()
            ->all();
    }

    /** @return array{percent: string, direction: string}|null */
    private function revenueChange(Carbon $from, Carbon $to, string $revenue): ?array
    {
        $days = $from->diffInDays($to) + 1;
        $previousTo = $from->copy()->subDay();
        $previousFrom = $previousTo->copy()->subDays($days - 1);

        $previousRevenue = $this->sum($this->billRepository->forDateRange($previousFrom, $previousTo), 'total');

        if (bccomp($previousRevenue, '0', 2) === 0) {
            return null;
        }

        $difference = bcsub($revenue, $previousRevenue, 2);

        return [
            'percent' => number_format(abs((float) bcmul(bcdiv($difference, $previousRevenue, 4), '100', 2)), 1),
            'direction' => bccomp($difference, '0', 2) >= 0 ? 'up' : 'down',
        ];
    }

    /**
     * @param  Collection<int, Bill>  $bills
     * @return array<int, array{label: string, amount: string, short: string, footfall: int}>
     */
    private function dailyRevenue(Carbon $from, Carbon $to, Collection $bills): array
    {
        $start = $to->copy()->subDays(self::MaxTrendDays - 1)->max($from);
        $byDay = $bills->groupBy(fn (Bill $bill): string => $bill->created_at->format('Y-m-d'));

        $days = [];

        for ($day = $start->copy(); $day->lte($to); $day->addDay()) {
            $dayBills = $byDay->get($day->format('Y-m-d'), collect());
            $amount = $this->sum($dayBills, 'total');

            $days[] = [
                'label' => $day->format('d M'),
                'amount' => $amount,
                'short' => $this->shortAmount((float) $amount),
                'footfall' => $dayBills->pluck('client_id')->unique()->count(),
            ];
        }

        return $days;
    }

    private function shortAmount(float $amount): string
    {
        if ($amount >= 100000) {
            return rtrim(rtrim(number_format($amount / 100000, 1), '0'), '.').'L';
        }

        if ($amount >= 1000) {
            return rtrim(rtrim(number_format($amount / 1000, 1), '0'), '.').'k';
        }

        return number_format($amount, 0);
    }
}
