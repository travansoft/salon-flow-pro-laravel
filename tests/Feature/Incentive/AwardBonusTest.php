<?php

namespace Tests\Feature\Incentive;

use App\Models\StaffProfile;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsTenant;
use Tests\TestCase;

class AwardBonusTest extends TestCase
{
    use ActsAsTenant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenant();
        $this->seed(PermissionSeeder::class);
    }

    public function test_owner_can_award_a_bonus(): void
    {
        $owner = User::factory()->for($this->tenant)->create();
        $owner->assignRole('Owner');
        $staff = StaffProfile::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($owner)->postToTenant('/staff-bonuses', [
            'staff_profile_id' => $staff->id,
            'amount' => 500,
            'reason' => 'Top performer',
            'awarded_date' => '2026-06-10',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('staff_incentives', [
            'tenant_id' => $this->tenant->id,
            'staff_profile_id' => $staff->id,
            'reason' => 'Top performer',
            'awarded_by' => $owner->id,
        ]);
    }

    public function test_a_correction_is_recorded_as_a_new_negative_amount_entry(): void
    {
        $owner = User::factory()->for($this->tenant)->create();
        $owner->assignRole('Owner');
        $staff = StaffProfile::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAs($owner)->postToTenant('/staff-bonuses', [
            'staff_profile_id' => $staff->id,
            'amount' => 500,
            'reason' => 'Top performer',
            'awarded_date' => '2026-06-10',
        ]);

        $response = $this->actingAs($owner)->postToTenant('/staff-bonuses', [
            'staff_profile_id' => $staff->id,
            'amount' => -500,
            'reason' => 'Correction: awarded in error',
            'awarded_date' => '2026-06-11',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('staff_incentives', [
            'staff_profile_id' => $staff->id,
            'amount' => -500,
        ]);
        $this->assertSame(2, $staff->incentives()->count());
    }

    public function test_stylist_cannot_award_a_bonus(): void
    {
        $stylist = User::factory()->for($this->tenant)->create();
        $stylist->assignRole('Stylist');
        $staff = StaffProfile::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($stylist)->postToTenant('/staff-bonuses', [
            'staff_profile_id' => $staff->id,
            'amount' => 500,
            'reason' => 'Top performer',
            'awarded_date' => '2026-06-10',
        ]);

        $response->assertForbidden();
    }

    public function test_validation_rejects_missing_reason(): void
    {
        $owner = User::factory()->for($this->tenant)->create();
        $owner->assignRole('Owner');
        $staff = StaffProfile::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($owner)->postToTenant('/staff-bonuses', [
            'staff_profile_id' => $staff->id,
            'amount' => 500,
            'awarded_date' => '2026-06-10',
        ]);

        $response->assertSessionHasErrors('reason');
    }
}
