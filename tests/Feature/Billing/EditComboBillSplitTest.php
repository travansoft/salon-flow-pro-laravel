<?php

namespace Tests\Feature\Billing;

use App\Models\Bill;
use App\Models\BillLineItem;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsTenant;
use Tests\Concerns\CreatesCombos;
use Tests\Concerns\CreatesIncentiveBills;
use Tests\TestCase;

class EditComboBillSplitTest extends TestCase
{
    use ActsAsTenant, CreatesCombos, CreatesIncentiveBills, RefreshDatabase;

    private User $owner;

    private Service $combo;

    private StaffProfile $referrer;

    private Bill $bill;

    private BillLineItem $line;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenant();
        $this->seed(PermissionSeeder::class);
        $this->useTenantAndBranchContext();

        $this->owner = User::factory()->for($this->tenant)->create();
        $this->owner->assignRole('Owner');
        $this->assignToBranch($this->owner);

        $this->combo = $this->comboWith([700, 300]);
        $this->referrer = StaffProfile::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->bill = $this->billWithLine('1000', StaffProfile::factory()->create(['tenant_id' => $this->tenant->id]), $this->referrer, gst: '180', discount: '100');
        $this->line = $this->bill->lineItems()->firstOrFail();
        $this->line->update(['service_id' => $this->combo->id]);
    }

    /** @return array<string, mixed> */
    private function payload(array $split): array
    {
        return [
            'items' => [$this->line->id => ['staff_profile_id' => $this->line->staff_profile_id, 'referred_by_staff_profile_id' => $this->referrer->id]],
            'combo_split' => [$this->line->id => $split],
        ];
    }

    /** @return array<int, int> */
    private function fullSplit(): array
    {
        return collect($this->eligibleComponentStaff($this->combo))->pluck('staff_profile_id', 'service_id')->all();
    }

    public function test_edit_form_offers_a_staff_pick_per_service_of_an_old_combo_line(): void
    {
        $this->eligibleComponentStaff($this->combo);

        $response = $this->actingAs($this->owner)->getFromTenant("/bills/{$this->bill->id}/edit");

        $response->assertOk()->assertSee($this->combo->comboItems[0]->component->name)->assertSee('billed as one line');
    }

    public function test_owner_can_split_an_old_combo_line_into_its_services(): void
    {
        $response = $this->actingAs($this->owner)->putToTenant("/bills/{$this->bill->id}", $this->payload($this->fullSplit()));

        $response->assertRedirect();
        $this->assertDatabaseMissing('bill_line_items', ['id' => $this->line->id]);
        $lines = $this->bill->lineItems()->orderBy('id')->get();
        $this->assertCount(2, $lines);
        $this->assertSame($lines[0]->combo_group, $lines[1]->combo_group);
        $this->assertSame($this->combo->id, $lines[0]->combo_service_id);
        $this->assertSame([$this->referrer->id, $this->referrer->id], $lines->pluck('referred_by_staff_profile_id')->all());
    }

    public function test_split_keeps_the_line_amounts_discount_and_gst_unchanged(): void
    {
        $this->actingAs($this->owner)->putToTenant("/bills/{$this->bill->id}", $this->payload($this->fullSplit()));

        $lines = $this->bill->lineItems()->get();
        $this->assertSame('1000.00', $lines->reduce(fn (string $sum, BillLineItem $line) => bcadd($sum, (string) $line->line_total, 2), '0'));
        $this->assertSame('100.00', $lines->reduce(fn (string $sum, BillLineItem $line) => bcadd($sum, (string) $line->discount_amount, 2), '0'));
        $this->assertSame('90.00', $lines->reduce(fn (string $sum, BillLineItem $line) => bcadd($sum, (string) $line->cgst_amount, 2), '0'));
        $this->assertSame('700.00', (string) $lines->firstWhere('service_id', $this->combo->comboItems[0]->component_service_id)->line_total);
    }

    public function test_split_is_recorded_in_the_bill_history(): void
    {
        $this->actingAs($this->owner)->putToTenant("/bills/{$this->bill->id}", $this->payload($this->fullSplit()));

        $this->assertDatabaseHas('bill_audits', ['bill_id' => $this->bill->id, 'field' => 'combo_split']);
    }

    public function test_split_needs_staff_for_every_service(): void
    {
        $split = $this->fullSplit();
        array_pop($split);

        $response = $this->actingAs($this->owner)->putToTenant("/bills/{$this->bill->id}", $this->payload($split));

        $response->assertSessionHasErrors('combo_split');
        $this->assertDatabaseHas('bill_line_items', ['id' => $this->line->id]);
    }

    public function test_split_rejects_staff_not_eligible_for_the_service(): void
    {
        $split = $this->fullSplit();
        $firstComponentId = array_key_first($split);
        $split[$firstComponentId] = StaffProfile::factory()->create(['tenant_id' => $this->tenant->id])->id;

        $response = $this->actingAs($this->owner)->putToTenant("/bills/{$this->bill->id}", $this->payload($split));

        $response->assertSessionHasErrors('combo_split');
        $this->assertDatabaseHas('bill_line_items', ['id' => $this->line->id]);
    }

    public function test_a_line_that_is_not_a_combo_cannot_be_split(): void
    {
        $this->line->update(['service_id' => null]);

        $response = $this->actingAs($this->owner)->putToTenant("/bills/{$this->bill->id}", $this->payload($this->fullSplit()));

        $response->assertSessionHasErrors('combo_split');
    }

    public function test_front_desk_cannot_split_a_combo_line(): void
    {
        $frontDesk = User::factory()->for($this->tenant)->create();
        $frontDesk->assignRole('FrontDesk');
        $this->assignToBranch($frontDesk);

        $response = $this->actingAs($frontDesk)->putToTenant("/bills/{$this->bill->id}", $this->payload($this->fullSplit()));

        $response->assertForbidden();
        $this->assertDatabaseHas('bill_line_items', ['id' => $this->line->id]);
    }

    public function test_changing_the_referrer_of_one_combo_line_applies_to_the_whole_combo(): void
    {
        $this->actingAs($this->owner)->putToTenant("/bills/{$this->bill->id}", $this->payload($this->fullSplit()));
        $lines = $this->bill->lineItems()->orderBy('id')->get();
        $newReferrer = StaffProfile::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAs($this->owner)->putToTenant("/bills/{$this->bill->id}", [
            'items' => $lines->mapWithKeys(fn (BillLineItem $line, int $position) => [$line->id => [
                'staff_profile_id' => $line->staff_profile_id,
                'referred_by_staff_profile_id' => $position === 0 ? $newReferrer->id : $line->referred_by_staff_profile_id,
            ]])->all(),
        ]);

        $this->assertSame([$newReferrer->id, $newReferrer->id], $this->bill->lineItems()->orderBy('id')->pluck('referred_by_staff_profile_id')->all());
    }
}
