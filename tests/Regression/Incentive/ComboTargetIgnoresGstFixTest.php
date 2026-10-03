<?php

namespace Tests\Regression\Incentive;

use App\Models\User;
use App\Services\IncentiveService;
use App\Services\QuickBillService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\ActsAsTenant;
use Tests\Concerns\CreatesCombos;
use Tests\Concerns\CreatesIncentiveBills;
use Tests\TestCase;

class ComboTargetIgnoresGstFixTest extends TestCase
{
    use ActsAsTenant, CreatesCombos, CreatesIncentiveBills, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenant();
        $this->useTenantAndBranchContext();
        $this->tenant->update(['default_gst_rate' => 18]);
    }

    /**
     * Bug: the credit for a combo service was rebuilt from the GST-split bill
     * line, so it came out a few paise above or below the amount given.
     */
    public function test_combo_service_credit_is_exactly_the_amount_given_without_gst_rounding(): void
    {
        $combo = $this->comboWith([233, 117, 199]);
        $components = $this->eligibleComponentStaff($combo);
        $user = User::factory()->for($this->tenant)->create();

        app(QuickBillService::class)->createAndSettle([['service_id' => $combo->id, 'components' => $components]], [], 'cash', $user->id);

        $progress = app(IncentiveService::class)->progressForAll(Carbon::now());
        $this->assertSame('233.00', $progress->get($components[0]['staff_profile_id'])['servicingCredit']);
        $this->assertSame('117.00', $progress->get($components[1]['staff_profile_id'])['servicingCredit']);
        $this->assertSame('199.00', $progress->get($components[2]['staff_profile_id'])['servicingCredit']);
    }

    public function test_overridden_combo_price_is_what_gets_credited_across_the_services(): void
    {
        $combo = $this->comboWith([500, 300, 200], 900);
        $components = $this->eligibleComponentStaff($combo);
        $user = User::factory()->for($this->tenant)->create();

        app(QuickBillService::class)->createAndSettle([['service_id' => $combo->id, 'components' => $components]], [], 'cash', $user->id);

        $progress = app(IncentiveService::class)->progressForAll(Carbon::now());
        $credited = collect($components)->reduce(fn (string $sum, array $component) => bcadd($sum, $progress->get($component['staff_profile_id'])['servicingCredit'], 2), '0');
        $this->assertSame('900.00', $credited);
    }
}
