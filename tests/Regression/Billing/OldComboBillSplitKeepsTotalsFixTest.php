<?php

namespace Tests\Regression\Billing;

use App\Models\BillLineItem;
use App\Models\StaffProfile;
use App\Models\User;
use App\Services\BillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsTenant;
use Tests\Concerns\CreatesCombos;
use Tests\Concerns\CreatesIncentiveBills;
use Tests\TestCase;

class OldComboBillSplitKeepsTotalsFixTest extends TestCase
{
    use ActsAsTenant, CreatesCombos, CreatesIncentiveBills, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenant();
        $this->useTenantAndBranchContext();
    }

    /**
     * Risk: splitting an old single-line combo bill into services must never
     * move the bill's subtotal, discount, GST or total, even when the amounts
     * do not divide evenly between the services.
     */
    public function test_splitting_an_uneven_combo_line_leaves_bill_totals_untouched(): void
    {
        $combo = $this->comboWith([100, 100, 100]);
        $bill = $this->billWithLine('1000.01', StaffProfile::factory()->create(['tenant_id' => $this->tenant->id]), gst: '180.01', discount: '33.33');
        $bill->lineItems()->firstOrFail()->update(['service_id' => $combo->id]);
        $originalCgst = (string) $bill->lineItems()->firstOrFail()->cgst_amount;
        $editor = User::factory()->for($this->tenant)->create();
        $split = collect($this->eligibleComponentStaff($combo))->pluck('staff_profile_id', 'service_id')->all();

        app(BillingService::class)->editBill($bill, $bill->client_id, $bill->notes, null, $editor->id, [], [$bill->lineItems()->firstOrFail()->id => $split]);

        $lines = BillLineItem::query()->where('bill_id', $bill->id)->get();
        $this->assertCount(3, $lines);
        $this->assertSame('1000.01', $lines->reduce(fn (string $sum, BillLineItem $line) => bcadd($sum, (string) $line->line_total, 2), '0'));
        $this->assertSame('33.33', $lines->reduce(fn (string $sum, BillLineItem $line) => bcadd($sum, (string) $line->discount_amount, 2), '0'));
        $this->assertSame($originalCgst, $lines->reduce(fn (string $sum, BillLineItem $line) => bcadd($sum, (string) $line->cgst_amount, 2), '0'));
    }
}
