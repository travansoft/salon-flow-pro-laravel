<?php

namespace App\Services;

use App\Repositories\Contracts\ExpenseRepositoryInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ExpenseSummaryService
{
    public function __construct(private ExpenseRepositoryInterface $expenseRepository) {}

    /**
     * @return array{
     *     categories: Collection<int, array{category_id: ?int, name: string, count: int, total: string, share: float}>,
     *     total: string,
     *     count: int,
     * }
     */
    public function forRange(Carbon $from, Carbon $to): array
    {
        $rows = $this->expenseRepository->totalsByCategoryBetween($from, $to);

        $total = $rows->reduce(
            fn (string $carry, object $row): string => bcadd($carry, (string) $row->total, 2),
            '0.00',
        );

        $categories = $rows->map(fn (object $row): array => [
            'category_id' => $row->category_id !== null ? (int) $row->category_id : null,
            'name' => $row->name ?? 'Uncategorised',
            'count' => (int) $row->expense_count,
            'total' => bcadd((string) $row->total, '0', 2),
            'share' => bccomp($total, '0', 2) === 1
                ? round((float) bcdiv(bcmul((string) $row->total, '100', 4), $total, 4), 1)
                : 0.0,
        ])->values();

        return [
            'categories' => $categories,
            'total' => $total,
            'count' => $categories->sum('count'),
        ];
    }
}
