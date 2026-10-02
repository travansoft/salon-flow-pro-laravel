<?php

namespace Tests\Regression\Incentive;

use App\Models\StaffIncentive;
use App\Models\StaffProfile;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsTenant;
use Tests\TestCase;

class BonusEntryCanBeCorrectedInPlaceFixTest extends TestCase
{
    use ActsAsTenant, RefreshDatabase;

    /**
     * Bug: a wrongly awarded bonus could not be edited or removed, only offset
     * with a negative entry, leaving the list cluttered.
     */
    public function test_bonus_is_corrected_in_place_without_a_negative_offset_entry(): void
    {
        $this->setUpTenant();
        $this->seed(PermissionSeeder::class);
        $owner = User::factory()->for($this->tenant)->create();
        $owner->assignRole('Owner');
        $staff = StaffProfile::factory()->create(['tenant_id' => $this->tenant->id]);
        $bonus = StaffIncentive::factory()->create(['tenant_id' => $this->tenant->id, 'staff_profile_id' => $staff->id, 'amount' => 500, 'awarded_date' => '2026-06-10']);

        $this->actingAs($owner)->putToTenant("/staff-bonuses/{$bonus->id}", [
            'staff_profile_id' => $staff->id,
            'amount' => 450,
            'reason' => 'Corrected',
            'awarded_date' => '2026-06-10',
        ]);

        $this->assertSame(1, $staff->incentives()->count());
        $this->assertDatabaseHas('staff_incentives', ['id' => $bonus->id, 'amount' => 450]);
    }
}
