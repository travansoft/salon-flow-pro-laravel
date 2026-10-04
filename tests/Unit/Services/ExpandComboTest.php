<?php

namespace Tests\Unit\Services;

use App\Actions\ExpandCombo;
use App\Models\StaffProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\Concerns\ActsAsTenant;
use Tests\Concerns\CreatesCombos;
use Tests\Concerns\CreatesIncentiveBills;
use Tests\TestCase;

class ExpandComboTest extends TestCase
{
    use ActsAsTenant, CreatesCombos, CreatesIncentiveBills, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenant();
        $this->useTenantAndBranchContext();
    }

    public function test_combo_expands_into_one_line_per_component_with_the_chosen_staff(): void
    {
        $combo = $this->comboWith([500, 300]);
        $components = $this->eligibleComponentStaff($combo);

        $lines = app(ExpandCombo::class)->execute(['service_id' => $combo->id, 'components' => $components]);

        $this->assertCount(2, $lines);
        $this->assertSame($components[0]['staff_profile_id'], $lines[0]['staff_profile_id']);
        $this->assertSame($components[1]['staff_profile_id'], $lines[1]['staff_profile_id']);
        $this->assertSame($combo->id, $lines[0]['combo_service_id']);
        $this->assertSame($lines[0]['combo_group'], $lines[1]['combo_group']);
    }

    public function test_component_prices_keep_their_own_values_when_combo_price_equals_their_total(): void
    {
        $combo = $this->comboWith([500, 300]);

        $lines = app(ExpandCombo::class)->execute(['service_id' => $combo->id, 'components' => $this->eligibleComponentStaff($combo)]);

        $this->assertSame([500.0, 300.0], array_column($lines, 'unit_price'));
    }

    public function test_overridden_combo_price_is_spread_proportionally_and_sums_exactly(): void
    {
        $combo = $this->comboWith([500, 300, 200], 900);

        $lines = app(ExpandCombo::class)->execute(['service_id' => $combo->id, 'components' => $this->eligibleComponentStaff($combo)]);

        $this->assertSame([450.0, 270.0, 180.0], array_column($lines, 'unit_price'));
    }

    public function test_rounding_remainder_is_absorbed_by_the_last_line(): void
    {
        $combo = $this->comboWith([100, 100, 100], 100);

        $lines = app(ExpandCombo::class)->execute(['service_id' => $combo->id, 'components' => $this->eligibleComponentStaff($combo)]);

        $this->assertSame('100.00', bcadd(bcadd((string) $lines[0]['unit_price'], (string) $lines[1]['unit_price'], 2), (string) $lines[2]['unit_price'], 2));
    }

    public function test_referrer_is_stamped_on_every_line_of_the_combo(): void
    {
        $combo = $this->comboWith([500, 300]);
        $referrer = StaffProfile::factory()->create(['tenant_id' => $this->tenant->id]);

        $lines = app(ExpandCombo::class)->execute([
            'service_id' => $combo->id,
            'components' => $this->eligibleComponentStaff($combo),
            'referred_by_staff_profile_id' => $referrer->id,
        ]);

        $this->assertSame([$referrer->id, $referrer->id], array_column($lines, 'referred_by_staff_profile_id'));
    }

    public function test_component_without_staff_is_left_unassigned(): void
    {
        $combo = $this->comboWith([500, 300]);
        $components = $this->eligibleComponentStaff($combo);
        $components[1]['staff_profile_id'] = null;

        $lines = app(ExpandCombo::class)->execute(['service_id' => $combo->id, 'components' => $components]);

        $this->assertSame($components[0]['staff_profile_id'], $lines[0]['staff_profile_id']);
        $this->assertNull($lines[1]['staff_profile_id']);
    }

    public function test_component_that_is_not_part_of_the_combo_is_rejected(): void
    {
        $combo = $this->comboWith([500, 300]);
        $stranger = $this->comboWith([100, 100]);
        $components = $this->eligibleComponentStaff($combo);
        $components[0]['service_id'] = $stranger->comboItems[0]->component_service_id;

        $this->expectException(InvalidArgumentException::class);

        app(ExpandCombo::class)->execute(['service_id' => $combo->id, 'components' => $components]);
    }

    public function test_ineligible_staff_for_a_component_is_rejected(): void
    {
        $combo = $this->comboWith([500, 300]);
        $components = $this->eligibleComponentStaff($combo);
        $components[0]['staff_profile_id'] = StaffProfile::factory()->create(['tenant_id' => $this->tenant->id])->id;

        $this->expectException(InvalidArgumentException::class);

        app(ExpandCombo::class)->execute(['service_id' => $combo->id, 'components' => $components]);
    }
}
