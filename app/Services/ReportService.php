<?php

namespace App\Services;

use App\Models\Bill;
use App\Repositories\Contracts\BillRepositoryInterface;
use App\Repositories\Contracts\ProductRepositoryInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ReportService
{
    public function __construct(
        private BillRepositoryInterface $billRepository,
        private ProductRepositoryInterface $productRepository,
    ) {}

    /** @return array<string, mixed> */
    public function reportFor(Carbon $from, Carbon $to): array
    {
        $paidBills = $this->billRepository->forDateRange($from, $to);

        return [
            'totalRevenue' => $this->totalRevenue($paidBills),
            'billCount' => $paidBills->count(),
            'lowStockCount' => $this->productRepository->getLowStock()->count(),
            'revenueTrend' => $this->revenueTrend($to),
            'topServices' => $this->topServicesByRevenue($paidBills),
            'paymentMix' => $this->paymentMethodMix($paidBills),
            'staffPerformance' => $this->staffPerformance($paidBills),
        ];
    }

    /** @param Collection<int, Bill> $bills */
    private function totalRevenue($bills): string
    {
        return $bills->reduce(fn (string $carry, Bill $bill) => bcadd($carry, (string) $bill->total, 2), '0.00');
    }

    /** @return array<int, array{label: string, amount: string}> */
    private function revenueTrend(Carbon $to): array
    {
        $trend = [];

        for ($i = 9; $i >= 0; $i--) {
            $day = $to->copy()->subDays($i);

            $amount = $this->billRepository->totalForDate($day);

            $trend[] = [
                'label' => $day->format('d M'),
                'amount' => number_format((float) $amount, 2, '.', ''),
            ];
        }

        return $trend;
    }

    /**
     * @param  Collection<int, Bill>  $bills
     * @return array<int, array{name: string, amount: string}>
     */
    private function topServicesByRevenue($bills): array
    {
        $totals = [];

        foreach ($bills as $bill) {
            foreach ($bill->lineItems as $lineItem) {
                $name = $lineItem->service?->name ?? $lineItem->description;
                $totals[$name] = bcadd($totals[$name] ?? '0.00', (string) $lineItem->line_total, 2);
            }
        }

        arsort($totals);

        return collect($totals)
            ->take(5)
            ->map(fn (string $amount, string $name) => ['name' => $name, 'amount' => $amount])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Bill>  $bills
     * @return array<int, array{method: string, amount: string}>
     */
    private function paymentMethodMix($bills): array
    {
        $totals = [];

        foreach ($bills as $bill) {
            foreach ($bill->payments as $payment) {
                $totals[$payment->method] = bcadd($totals[$payment->method] ?? '0.00', (string) $payment->amount, 2);
            }
        }

        arsort($totals);

        return collect($totals)
            ->map(fn (string $amount, string $method) => ['method' => $method, 'amount' => $amount])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Bill>  $bills
     * @return array<int, array{name: string, services: int, revenue: string}>
     */
    private function staffPerformance($bills): array
    {
        $totals = [];

        foreach ($bills as $bill) {
            foreach ($bill->lineItems as $lineItem) {
                $staffName = $lineItem->staffProfile?->name;

                if (! $staffName) {
                    continue;
                }

                if (! isset($totals[$staffName])) {
                    $totals[$staffName] = ['services' => 0, 'revenue' => '0.00'];
                }

                $totals[$staffName]['services']++;
                $totals[$staffName]['revenue'] = bcadd($totals[$staffName]['revenue'], (string) $lineItem->line_total, 2);
            }
        }

        uasort($totals, fn (array $a, array $b) => bccomp($b['revenue'], $a['revenue'], 2));

        return collect($totals)
            ->map(fn (array $row, string $name) => ['name' => $name, ...$row])
            ->values()
            ->all();
    }
}
