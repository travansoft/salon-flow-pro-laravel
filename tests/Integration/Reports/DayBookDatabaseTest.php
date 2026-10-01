<?php

namespace Tests\Integration\Reports;

use App\Models\Bill;
use App\Models\BillPayment;
use App\Models\BillRefund;
use App\Models\Branch;
use App\Models\Expense;
use App\Models\Tenant;
use App\Services\BranchContext;
use App\Services\DayBookService;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DayBookDatabaseTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($this->tenant);
        app(BranchContext::class)->set(Branch::factory()->create(['tenant_id' => $this->tenant->id]));
    }

    public function test_opening_balance_carries_forward_earlier_days_and_closing_includes_today(): void
    {
        $today = Carbon::today();

        $bill = Bill::factory()->paid()->create(['tenant_id' => $this->tenant->id, 'total' => 2000]);

        $this->payment($bill, 'cash', 1000, $today->copy()->subDay());
        $this->payment($bill, 'upi', 400, $today->copy()->subDay());
        $this->payment($bill, 'cash', 600, $today->copy()->setTime(10, 0));
        $this->payment($bill, 'upi', 250, $today->copy()->setTime(11, 0));

        $refund = BillRefund::create([
            'tenant_id' => $this->tenant->id,
            'bill_id' => $bill->id,
            'amount' => 50,
            'method' => 'upi',
            'reason' => 'Unhappy',
            'refunded_by' => $bill->created_by,
        ]);
        $refund->forceFill(['created_at' => $today->copy()->setTime(12, 0)])->save();

        $this->expense('cash', 300, $today->copy()->subDay());
        $this->expense('cash', 100, $today);

        $result = app(DayBookService::class)->forRange($today, $today);

        $this->assertSame(['cash' => '700.00', 'upi' => '400.00'], $result['opening']);
        $this->assertSame(['cash' => '1200.00', 'upi' => '600.00'], $result['closing']);
        $this->assertCount(4, $result['entries']);
    }

    public function test_void_bills_are_left_out_of_the_day_book(): void
    {
        $today = Carbon::today();

        $bill = Bill::factory()->create(['tenant_id' => $this->tenant->id, 'status' => Bill::StatusVoid]);
        $this->payment($bill, 'cash', 900, $today);

        $result = app(DayBookService::class)->forRange($today, $today);

        $this->assertCount(0, $result['entries']);
        $this->assertSame('0.00', $result['closing']['cash']);
    }

    private function payment(Bill $bill, string $method, float $amount, Carbon $at): void
    {
        BillPayment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'bill_id' => $bill->id,
            'method' => $method,
            'amount' => $amount,
            'created_at' => $at,
        ]);
    }

    private function expense(string $method, float $amount, Carbon $date): void
    {
        Expense::factory()->create([
            'tenant_id' => $this->tenant->id,
            'payment_method' => $method,
            'amount' => $amount,
            'expense_date' => $date->format('Y-m-d'),
        ]);
    }
}
