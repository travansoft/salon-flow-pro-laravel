<?php

namespace Tests\Unit\Reports;

use App\Repositories\Contracts\ExpenseRepositoryInterface;
use App\Services\ExpenseSummaryService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Mockery;
use Tests\TestCase;

class ExpenseSummaryServiceTest extends TestCase
{
    public function test_for_range_totals_categories_and_computes_shares(): void
    {
        $from = Carbon::parse('2026-10-01');
        $to = Carbon::parse('2026-10-31');

        $repository = Mockery::mock(ExpenseRepositoryInterface::class);
        $repository->shouldReceive('totalsByCategoryBetween')
            ->once()
            ->with($from, $to)
            ->andReturn(new Collection([
                (object) ['category_id' => 1, 'name' => 'Rent', 'expense_count' => 1, 'total' => '7500.00'],
                (object) ['category_id' => null, 'name' => null, 'expense_count' => 3, 'total' => '2500.00'],
            ]));

        $result = (new ExpenseSummaryService($repository))->forRange($from, $to);

        $this->assertSame('10000.00', $result['total']);
        $this->assertSame(4, $result['count']);
        $this->assertSame('Rent', $result['categories'][0]['name']);
        $this->assertSame(75.0, $result['categories'][0]['share']);
        $this->assertNull($result['categories'][1]['category_id']);
        $this->assertSame('Uncategorised', $result['categories'][1]['name']);
        $this->assertSame(25.0, $result['categories'][1]['share']);
    }

    public function test_for_range_with_no_expenses_returns_zero_totals_without_dividing(): void
    {
        $repository = Mockery::mock(ExpenseRepositoryInterface::class);
        $repository->shouldReceive('totalsByCategoryBetween')->andReturn(new Collection);

        $result = (new ExpenseSummaryService($repository))->forRange(Carbon::today(), Carbon::today());

        $this->assertSame('0.00', $result['total']);
        $this->assertSame(0, $result['count']);
        $this->assertTrue($result['categories']->isEmpty());
    }
}
