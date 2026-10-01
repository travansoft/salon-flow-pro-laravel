<?php

namespace Tests\Unit\Reports;

use App\Models\Bill;
use App\Models\BillPayment;
use App\Models\BillRefund;
use App\Models\Expense;
use App\Repositories\Contracts\BillRepositoryInterface;
use App\Repositories\Contracts\ExpenseRepositoryInterface;
use App\Services\DayBookService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Mockery;
use Tests\TestCase;

class DayBookServiceTest extends TestCase
{
    public function test_closing_balances_roll_opening_forward_by_method(): void
    {
        $today = Carbon::parse('2026-10-01');

        $bill = new Bill(['bill_number' => 1]);
        $bill->id = 7;

        $payment = new BillPayment(['method' => 'cash', 'amount' => '500.00']);
        $payment->setRelation('bill', $bill);
        $payment->created_at = $today->copy()->setTime(10, 0);

        $upiPayment = new BillPayment(['method' => 'upi', 'amount' => '300.00']);
        $upiPayment->setRelation('bill', $bill);
        $upiPayment->created_at = $today->copy()->setTime(11, 0);

        $refund = new BillRefund(['method' => 'upi', 'amount' => '100.00', 'reason' => 'Unhappy']);
        $refund->setRelation('bill', $bill);
        $refund->created_at = $today->copy()->setTime(12, 0);

        $expense = new Expense(['payment_method' => 'cash', 'amount' => '200.00', 'description' => 'Tea']);
        $expense->expense_date = $today;

        $bills = Mockery::mock(BillRepositoryInterface::class);
        $bills->shouldReceive('paymentsBetween')->andReturn(new Collection([$payment, $upiPayment]));
        $bills->shouldReceive('refundsBetween')->andReturn(new Collection([$refund]));
        $bills->shouldReceive('paymentTotalsByMethodBefore')->andReturn(['cash' => '1000.00']);
        $bills->shouldReceive('refundTotalsByMethodBefore')->andReturn([]);

        $expenses = Mockery::mock(ExpenseRepositoryInterface::class);
        $expenses->shouldReceive('getBetweenDates')->andReturn(new Collection([$expense]));
        $expenses->shouldReceive('totalsByMethodBefore')->andReturn(['cash' => '100.00']);

        $result = (new DayBookService($bills, $expenses))->forRange($today, $today);

        $this->assertSame(['cash' => '900.00', 'upi' => '0.00'], $result['opening']);
        $this->assertSame(['cash' => '1200.00', 'upi' => '200.00'], $result['closing']);
        $this->assertCount(4, $result['entries']);
        $this->assertSame('300.00', $result['totals']['upi']['in']);
        $this->assertSame('100.00', $result['totals']['upi']['out']);
    }

    public function test_card_entries_do_not_affect_cash_or_upi_closing(): void
    {
        $today = Carbon::parse('2026-10-01');

        $bill = new Bill(['bill_number' => 2]);
        $bill->id = 8;

        $payment = new BillPayment(['method' => 'card', 'amount' => '750.00']);
        $payment->setRelation('bill', $bill);
        $payment->created_at = $today;

        $bills = Mockery::mock(BillRepositoryInterface::class);
        $bills->shouldReceive('paymentsBetween')->andReturn(new Collection([$payment]));
        $bills->shouldReceive('refundsBetween')->andReturn(new Collection);
        $bills->shouldReceive('paymentTotalsByMethodBefore')->andReturn([]);
        $bills->shouldReceive('refundTotalsByMethodBefore')->andReturn([]);

        $expenses = Mockery::mock(ExpenseRepositoryInterface::class);
        $expenses->shouldReceive('getBetweenDates')->andReturn(new Collection);
        $expenses->shouldReceive('totalsByMethodBefore')->andReturn([]);

        $result = (new DayBookService($bills, $expenses))->forRange($today, $today);

        $this->assertSame(['cash' => '0.00', 'upi' => '0.00'], $result['closing']);
        $this->assertSame('750.00', $result['totals']['card']['in']);
    }
}
