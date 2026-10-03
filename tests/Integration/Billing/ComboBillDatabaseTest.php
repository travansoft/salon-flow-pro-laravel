<?php

namespace Tests\Integration\Billing;

use App\Models\Bill;
use App\Models\BillLineItem;
use App\Models\StaffProfile;
use App\Models\User;
use App\Services\IncentiveService;
use App\Services\QuickBillService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\ActsAsTenant;
use Tests\Concerns\CreatesCombos;
use Tests\Concerns\CreatesIncentiveBills;
use Tests\TestCase;

class ComboBillDatabaseTest extends TestCase
{
    use ActsAsTenant, CreatesCombos, CreatesIncentiveBills, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenant();
        $this->useTenantAndBranchContext();
    }

    private function settleCombo(array $item): Bill
    {
        $user = User::factory()->for($this->tenant)->create();

        return app(QuickBillService::class)->createAndSettle([$item], [], 'cash', $user->id);
    }

    public function test_combo_bill_persists_one_line_per_component_sharing_a_group(): void
    {
        $combo = $this->comboWith([500, 300]);
        $components = $this->eligibleComponentStaff($combo);

        $bill = $this->settleCombo(['service_id' => $combo->id, 'components' => $components]);

        $this->assertSame(2, BillLineItem::query()->where('bill_id', $bill->id)->where('combo_service_id', $combo->id)->count());
        $this->assertSame(1, BillLineItem::query()->where('bill_id', $bill->id)->distinct()->count('combo_group'));
        $this->assertDatabaseHas('bill_line_items', ['bill_id' => $bill->id, 'service_id' => $combo->comboItems[0]->component_service_id, 'staff_profile_id' => $components[0]['staff_profile_id']]);
    }

    public function test_each_component_counts_toward_its_own_staff_target(): void
    {
        $combo = $this->comboWith([500, 300]);
        $components = $this->eligibleComponentStaff($combo);
        $this->settleCombo(['service_id' => $combo->id, 'components' => $components]);

        $progress = app(IncentiveService::class)->progressForAll(Carbon::now());

        $this->assertEqualsWithDelta(500, (float) $progress->get($components[0]['staff_profile_id'])['servicingCredit'], 0.01);
        $this->assertEqualsWithDelta(300, (float) $progress->get($components[1]['staff_profile_id'])['servicingCredit'], 0.01);
    }

    public function test_combo_referral_is_credited_once_on_the_whole_combo_value(): void
    {
        $combo = $this->comboWith([500, 300]);
        $referrer = StaffProfile::factory()->create(['tenant_id' => $this->tenant->id]);
        $components = $this->eligibleComponentStaff($combo);
        $this->settleCombo(['service_id' => $combo->id, 'components' => $components, 'referred_by_staff_profile_id' => $referrer->id]);

        $progress = app(IncentiveService::class)->progressForAll(Carbon::now());

        $this->assertEqualsWithDelta(240, (float) $progress->get($referrer->id)['referralCredit'], 0.01);
        $this->assertEqualsWithDelta(350, (float) $progress->get($components[0]['staff_profile_id'])['servicingCredit'], 0.01);
        $this->assertEqualsWithDelta(210, (float) $progress->get($components[1]['staff_profile_id'])['servicingCredit'], 0.01);
    }
}
