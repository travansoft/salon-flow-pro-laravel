<?php

namespace Tests\Feature\Incentive;

use App\Models\StaffIncentive;
use App\Models\StaffProfile;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsTenant;
use Tests\TestCase;

class ManageBonusesTest extends TestCase
{
    use ActsAsTenant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenant();
        $this->seed(PermissionSeeder::class);
    }

    private function owner(): User
    {
        $owner = User::factory()->for($this->tenant)->create();
        $owner->assignRole('Owner');

        return $owner;
    }

    private function bonusFor(StaffProfile $staff, string $date, array $overrides = []): StaffIncentive
    {
        return StaffIncentive::factory()->create([
            'tenant_id' => $this->tenant->id,
            'staff_profile_id' => $staff->id,
            'awarded_date' => $date,
            ...$overrides,
        ]);
    }

    public function test_owner_sees_bonuses_for_the_month_only(): void
    {
        $staff = StaffProfile::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->bonusFor($staff, '2026-06-10', ['reason' => 'June praise']);
        $this->bonusFor($staff, '2026-07-02', ['reason' => 'July praise']);

        $response = $this->actingAs($this->owner())->getFromTenant('/staff-bonuses?month=2026-06');

        $response->assertOk();
        $response->assertSee('June praise');
        $response->assertDontSee('July praise');
    }

    public function test_list_can_be_filtered_to_one_staff_member(): void
    {
        $staff = StaffProfile::factory()->create(['tenant_id' => $this->tenant->id]);
        $otherStaff = StaffProfile::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->bonusFor($staff, '2026-06-10', ['reason' => 'Mine']);
        $this->bonusFor($otherStaff, '2026-06-10', ['reason' => 'Theirs']);

        $response = $this->actingAs($this->owner())
            ->getFromTenant("/staff-bonuses?month=2026-06&staff_profile_id={$staff->id}");

        $response->assertSee('Mine');
        $response->assertDontSee('Theirs');
    }

    public function test_owner_can_update_a_bonus(): void
    {
        $staff = StaffProfile::factory()->create(['tenant_id' => $this->tenant->id]);
        $bonus = $this->bonusFor($staff, '2026-06-10');

        $response = $this->actingAs($this->owner())->putToTenant("/staff-bonuses/{$bonus->id}", [
            'staff_profile_id' => $staff->id,
            'amount' => 750,
            'reason' => 'Adjusted',
            'awarded_date' => '2026-06-12',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('staff_incentives', [
            'id' => $bonus->id,
            'amount' => 750,
            'reason' => 'Adjusted',
        ]);
    }

    public function test_update_validation_rejects_missing_reason(): void
    {
        $staff = StaffProfile::factory()->create(['tenant_id' => $this->tenant->id]);
        $bonus = $this->bonusFor($staff, '2026-06-10');

        $response = $this->actingAs($this->owner())->putToTenant("/staff-bonuses/{$bonus->id}", [
            'staff_profile_id' => $staff->id,
            'amount' => 750,
            'awarded_date' => '2026-06-12',
        ]);

        $response->assertSessionHasErrors('reason');
    }

    public function test_owner_can_delete_a_bonus(): void
    {
        $staff = StaffProfile::factory()->create(['tenant_id' => $this->tenant->id]);
        $bonus = $this->bonusFor($staff, '2026-06-10');

        $response = $this->actingAs($this->owner())->deleteFromTenant("/staff-bonuses/{$bonus->id}");

        $response->assertRedirect();
        $this->assertDatabaseMissing('staff_incentives', ['id' => $bonus->id]);
    }

    public function test_editing_an_unknown_bonus_returns_not_found(): void
    {
        $response = $this->actingAs($this->owner())->getFromTenant('/staff-bonuses/999999/edit');

        $response->assertNotFound();
    }

    public function test_stylist_cannot_edit_or_delete_a_bonus(): void
    {
        $stylist = User::factory()->for($this->tenant)->create();
        $stylist->assignRole('Stylist');
        $staff = StaffProfile::factory()->create(['tenant_id' => $this->tenant->id]);
        $bonus = $this->bonusFor($staff, '2026-06-10');

        $this->actingAs($stylist)->getFromTenant("/staff-bonuses/{$bonus->id}/edit")->assertForbidden();
        $this->actingAs($stylist)->putToTenant("/staff-bonuses/{$bonus->id}", [
            'staff_profile_id' => $staff->id,
            'amount' => 1,
            'reason' => 'Nope',
            'awarded_date' => '2026-06-12',
        ])->assertForbidden();
        $this->actingAs($stylist)->deleteFromTenant("/staff-bonuses/{$bonus->id}")->assertForbidden();

        $this->assertDatabaseHas('staff_incentives', ['id' => $bonus->id]);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->getFromTenant('/staff-bonuses');

        $response->assertRedirect();
    }

    public function test_progress_bonus_amount_links_to_the_filtered_list(): void
    {
        $staff = StaffProfile::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->bonusFor($staff, '2026-06-10', ['amount' => 500]);

        $response = $this->actingAs($this->owner())->getFromTenant('/incentive-progress?month=2026-06');

        $response->assertSee("staff-bonuses?month=2026-06&amp;staff_profile_id={$staff->id}", false);
    }
}
