<?php

namespace Tests\Regression\Reports;

use App\Models\Bill;
use App\Models\BillPayment;
use App\Models\Branch;
use App\Models\Tenant;
use App\Services\BillingService;
use App\Services\BranchContext;
use App\Services\DayBookService;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DayBookRefundReducesChosenMethodFixTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Bug guard: refunds had no payment method, so a UPI refund would have
     * been counted against the cash drawer in the day book.
     */
    public function test_upi_refund_reduces_upi_closing_and_leaves_cash_untouched(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        app(BranchContext::class)->set(Branch::factory()->create(['tenant_id' => $tenant->id]));

        $bill = Bill::factory()->paid()->create(['tenant_id' => $tenant->id, 'total' => 1000, 'amount_paid' => 1000]);
        BillPayment::factory()->create(['tenant_id' => $tenant->id, 'bill_id' => $bill->id, 'method' => 'cash', 'amount' => 500]);
        BillPayment::factory()->create(['tenant_id' => $tenant->id, 'bill_id' => $bill->id, 'method' => 'upi', 'amount' => 500]);

        app(BillingService::class)->refund($bill, 200, 'Unhappy', $bill->created_by, 'upi');

        $today = Carbon::today();
        $result = app(DayBookService::class)->forRange($today, $today);

        $this->assertSame('500.00', $result['closing']['cash']);
        $this->assertSame('300.00', $result['closing']['upi']);
    }
}
