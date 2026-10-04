<?php

namespace Tests\Regression\Billing;

use App\Models\Bill;
use App\Models\BillLineItem;
use App\Models\StaffProfile;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsTenant;
use Tests\Concerns\CreatesIncentiveBills;
use Tests\TestCase;

class ComboEditShowsSingleReferrerFixTest extends TestCase
{
    use ActsAsTenant, CreatesIncentiveBills, RefreshDatabase;

    private User $owner;

    private Bill $bill;

    /** @var array<int, BillLineItem> */
    private array $comboLines;

    private StaffProfile $referrer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenant();
        $this->seed(PermissionSeeder::class);
        $this->useTenantAndBranchContext();

        $this->owner = User::factory()->for($this->tenant)->create();
        $this->owner->assignRole('Owner');
        $this->assignToBranch($this->owner);

        $this->referrer = StaffProfile::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->bill = $this->billWithLine('700', StaffProfile::factory()->create(['tenant_id' => $this->tenant->id]), $this->referrer);
        $first = $this->bill->lineItems()->firstOrFail();
        $first->update(['combo_group' => 'group-1']);

        $second = BillLineItem::factory()->create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'bill_id' => $this->bill->id,
            'staff_profile_id' => StaffProfile::factory()->create(['tenant_id' => $this->tenant->id])->id,
            'referred_by_staff_profile_id' => $this->referrer->id,
            'combo_group' => 'group-1',
        ]);

        $this->comboLines = [$first, $second];
    }

    public function test_combo_bill_edit_page_shows_one_referral_field_for_the_whole_combo(): void
    {
        $response = $this->actingAs($this->owner)->getFromTenant("/bills/{$this->bill->id}/edit");

        $response->assertOk();
        $response->assertSee("items[{$this->comboLines[0]->id}][referred_by_staff_profile_id]", false);
        $response->assertDontSee('<select name="items['.$this->comboLines[1]->id.'][referred_by_staff_profile_id]"', false);
        $response->assertSee('Referred by (whole combo)');
    }

    public function test_changing_the_combo_referrer_applies_to_every_sub_service(): void
    {
        $newReferrer = StaffProfile::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAs($this->owner)->putToTenant("/bills/{$this->bill->id}", [
            'client_id' => $this->bill->client_id,
            'items' => [
                $this->comboLines[0]->id => ['staff_profile_id' => $this->comboLines[0]->staff_profile_id, 'referred_by_staff_profile_id' => $newReferrer->id],
                $this->comboLines[1]->id => ['staff_profile_id' => $this->comboLines[1]->staff_profile_id, 'referred_by_staff_profile_id' => $this->referrer->id],
            ],
        ])->assertSessionHasNoErrors();

        foreach ($this->comboLines as $line) {
            $this->assertDatabaseHas('bill_line_items', ['id' => $line->id, 'referred_by_staff_profile_id' => $newReferrer->id]);
        }
    }
}
