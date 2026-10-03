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
     * Bug: splitting an old single-line combo bill spread the exclusive amount
     * and the GST separately, leaving each service a few paise off its price.
     */
    public function test_split_gives_each_service_exactly_its_price_and_keeps_the_payable_total(): void
    {
        $combo = $this->comboWith([250, 100, 649]);
        $bill = $this->billWithLine('846.62', StaffProfile::factory()->create(['tenant_id' => $this->tenant->id]), gst: '152.38');
        $bill->lineItems()->firstOrFail()->update(['service_id' => $combo->id]);
        $bill->update(['total' => '999.00']);
        $editor = User::factory()->for($this->tenant)->create();
        $split = collect($this->eligibleComponentStaff($combo))->pluck('staff_profile_id', 'service_id')->all();

        app(BillingService::class)->editBill($bill, $bill->client_id, $bill->notes, null, $editor->id, [], [$bill->lineItems()->firstOrFail()->id => $split]);

        $lines = BillLineItem::query()->where('bill_id', $bill->id)->orderBy('id')->get();
        $this->assertSame(['250.00', '100.00', '649.00'], $lines->map(fn (BillLineItem $line) => $line->totalWithTax())->all());
        $this->assertSame(['250.00', '100.00', '649.00'], $lines->map(fn (BillLineItem $line) => $line->targetValue())->all());
        $this->assertSame('999.00', (string) $bill->fresh()->total);
        $this->assertSame((string) $bill->fresh()->subtotal, $lines->reduce(fn (string $sum, BillLineItem $line) => bcadd($sum, (string) $line->line_total, 2), '0'));
    }
}
