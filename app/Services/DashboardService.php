<?php

namespace App\Services;

use App\Models\Bill;
use Illuminate\Support\Carbon;

class DashboardService
{
    /** @return array<string, mixed> */
    public function summaryFor(Carbon $from, ?Carbon $to = null): array
    {
        $to ??= $from;
        $day = $to;

        $periodBills = Bill::whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->where('status', '!=', Bill::StatusVoid)
            ->get();

        $monthStart = $day->copy()->startOfMonth();
        $previousMonthStart = $monthStart->copy()->subMonthNoOverflow();
        $previousMonthEnd = $monthStart->copy()->subDay()->endOfDay();

        $monthToDateRevenue = Bill::whereBetween('created_at', [$monthStart, $day->copy()->endOfDay()])
            ->where('status', '!=', Bill::StatusVoid)
            ->sum('total');

        $previousMonthToDateRevenue = Bill::whereBetween('created_at', [$previousMonthStart, $previousMonthEnd])
            ->where('status', '!=', Bill::StatusVoid)
            ->sum('total');

        $revenue = number_format((float) $periodBills->sum('total'), 2, '.', '');
        $customerCount = $periodBills->pluck('client_id')->unique()->count();
        $billCount = $periodBills->count();
        $averageBill = $billCount > 0 ? bcdiv($revenue, (string) $billCount, 2) : '0.00';

        $pendingPayments = Bill::unpaidOrPartial()->get()
            ->reduce(fn (string $carry, Bill $bill) => bcadd($carry, $bill->balanceDue(), 2), '0.00');

        return [
            'revenue' => $revenue,
            'customerCount' => $customerCount,
            'averageBill' => $averageBill,
            'topEmployee' => $this->topEmployeeFor($from, $to),
            'topService' => $this->topServiceFor($from, $to),
            'pendingPayments' => $pendingPayments,
            'monthRevenueChangePercent' => $this->percentChange($previousMonthToDateRevenue, $monthToDateRevenue),
        ];
    }

    /** @return array{name: string, revenue: string}|null */
    private function topEmployeeFor(Carbon $from, Carbon $to): ?array
    {
        $row = Bill::whereBetween('bills.created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->where('bills.status', '!=', Bill::StatusVoid)
            ->join('bill_line_items', 'bill_line_items.bill_id', '=', 'bills.id')
            ->join('staff_profiles', 'staff_profiles.id', '=', 'bill_line_items.staff_profile_id')
            ->join('users', 'users.id', '=', 'staff_profiles.user_id')
            ->selectRaw('users.name as staff_name, sum(bill_line_items.line_total) as revenue')
            ->groupBy('users.name')
            ->orderByDesc('revenue')
            ->first();

        if (! $row) {
            return null;
        }

        return ['name' => $row->staff_name, 'revenue' => number_format((float) $row->revenue, 2, '.', '')];
    }

    private function topServiceFor(Carbon $from, Carbon $to): ?string
    {
        $row = Bill::whereBetween('bills.created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->where('bills.status', '!=', Bill::StatusVoid)
            ->join('bill_line_items', 'bill_line_items.bill_id', '=', 'bills.id')
            ->join('services', 'services.id', '=', 'bill_line_items.service_id')
            ->selectRaw('services.name as service_name, sum(bill_line_items.quantity) as qty')
            ->groupBy('services.name')
            ->orderByDesc('qty')
            ->first();

        return $row?->service_name;
    }

    private function percentChange(string $previous, string $current): ?float
    {
        if (bccomp($previous, '0', 2) === 0) {
            return null;
        }

        $diff = bcsub($current, $previous, 4);

        return (float) bcmul(bcdiv($diff, $previous, 4), '100', 2);
    }
}
