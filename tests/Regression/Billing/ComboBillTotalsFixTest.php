<?php

namespace Tests\Regression\Billing;

use App\Models\User;
use App\Services\QuickBillService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsTenant;
use Tests\Concerns\CreatesCombos;
use Tests\Concerns\CreatesIncentiveBills;
use Tests\TestCase;

class ComboBillTotalsFixTest extends TestCase
{
    use ActsAsTenant, CreatesCombos, CreatesIncentiveBills, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenant();
        $this->useTenantAndBranchContext();
    }

    /**
     * Risk: an overridden combo price must not be lost by component prices
     * (which still add up to the old total) being billed instead.
     */
    public function test_overridden_combo_price_is_what_the_customer_is_billed(): void
    {
        $combo = $this->comboWith([500, 300, 200], 900);
        $user = User::factory()->for($this->tenant)->create();

        $bill = app(QuickBillService::class)->createAndSettle(
            [['service_id' => $combo->id, 'components' => $this->eligibleComponentStaff($combo)]],
            [],
            'cash',
            $user->id,
        );

        $this->assertSame('900.00', (string) $bill->total);
    }

    /**
     * Risk: editing a component price in the combo later must not rewrite
     * what past bills charged.
     */
    public function test_changing_the_combo_definition_does_not_change_an_existing_bill(): void
    {
        $combo = $this->comboWith([500, 300]);
        $user = User::factory()->for($this->tenant)->create();
        $bill = app(QuickBillService::class)->createAndSettle(
            [['service_id' => $combo->id, 'components' => $this->eligibleComponentStaff($combo)]],
            [],
            'cash',
            $user->id,
        );

        $combo->comboItems()->update(['price' => 9999]);
        $combo->update(['price' => 19998]);

        $this->assertSame('800.00', (string) $bill->fresh()->total);
        $this->assertSame(2, $bill->lineItems()->count());
    }

    /**
     * Risk: a combo whose rounding does not divide evenly must still bill the
     * exact combo price, not a price a paisa off.
     */
    public function test_combo_that_does_not_split_evenly_still_bills_the_exact_price(): void
    {
        $combo = $this->comboWith([100, 100, 100], '99.99');
        $user = User::factory()->for($this->tenant)->create();

        $bill = app(QuickBillService::class)->createAndSettle(
            [['service_id' => $combo->id, 'components' => $this->eligibleComponentStaff($combo)]],
            [],
            'cash',
            $user->id,
        );

        $this->assertSame('99.99', (string) $bill->total);
    }
}
