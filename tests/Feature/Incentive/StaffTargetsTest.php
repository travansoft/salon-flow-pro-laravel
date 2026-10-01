<?php

namespace Tests\Feature\Incentive;

use App\Models\StaffProfile;
use App\Models\StaffTarget;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsTenant;
use Tests\TestCase;

class StaffTargetsTest extends TestCase
{
    use ActsAsTenant, RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenant();
        $this->seed(PermissionSeeder::class);

        $this->owner = User::factory()->for($this->tenant)->create();
        $this->owner->assignRole('Owner');
    }

    public function test_owner_sees_the_staff_list_with_existing_targets(): void
    {
        $staff = StaffProfile::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Haina Begum']);
        StaffTarget::factory()->create([
            'tenant_id' => $this->tenant->id,
            'staff_profile_id' => $staff->id,
            'month' => '2026-06-01',
            'target_amount' => 200000,
        ]);

        $response = $this->actingAs($this->owner)->getFromTenant('/incentive-targets?month=2026-06');

        $response->assertOk();
        $response->assertSee('Haina Begum');
        $response->assertSee('200000.00');
    }

    public function test_owner_can_set_targets_for_a_month(): void
    {
        $rizwan = StaffProfile::factory()->create(['tenant_id' => $this->tenant->id]);
        $azam = StaffProfile::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($this->owner)->postToTenant('/incentive-targets', [
            'month' => '2026-06',
            'targets' => [$rizwan->id => 200000, $azam->id => 150000],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('staff_targets', ['staff_profile_id' => $rizwan->id, 'month' => '2026-06-01', 'target_amount' => 200000]);
        $this->assertDatabaseHas('staff_targets', ['staff_profile_id' => $azam->id, 'month' => '2026-06-01', 'target_amount' => 150000]);
    }

    public function test_saving_again_updates_instead_of_duplicating(): void
    {
        $staff = StaffProfile::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAs($this->owner)->postToTenant('/incentive-targets', ['month' => '2026-06', 'targets' => [$staff->id => 100000]]);
        $this->actingAs($this->owner)->postToTenant('/incentive-targets', ['month' => '2026-06', 'targets' => [$staff->id => 120000]]);

        $this->assertDatabaseCount('staff_targets', 1);
        $this->assertDatabaseHas('staff_targets', ['staff_profile_id' => $staff->id, 'target_amount' => 120000]);
    }

    public function test_blank_target_clears_an_existing_one(): void
    {
        $staff = StaffProfile::factory()->create(['tenant_id' => $this->tenant->id]);
        StaffTarget::factory()->create(['tenant_id' => $this->tenant->id, 'staff_profile_id' => $staff->id, 'month' => '2026-06-01']);

        $this->actingAs($this->owner)->postToTenant('/incentive-targets', ['month' => '2026-06', 'targets' => [$staff->id => '']]);

        $this->assertDatabaseMissing('staff_targets', ['staff_profile_id' => $staff->id]);
    }

    public function test_negative_target_is_rejected(): void
    {
        $staff = StaffProfile::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($this->owner)->postToTenant('/incentive-targets', ['month' => '2026-06', 'targets' => [$staff->id => -5]]);

        $response->assertSessionHasErrors('targets.'.$staff->id);
    }

    public function test_staff_from_another_tenant_is_rejected(): void
    {
        $otherStaff = StaffProfile::factory()->create(['tenant_id' => Tenant::factory()->create()->id]);

        $response = $this->actingAs($this->owner)->postToTenant('/incentive-targets', ['month' => '2026-06', 'targets' => [$otherStaff->id => 1000]]);

        $response->assertSessionHasErrors('targets');
        $this->assertDatabaseCount('staff_targets', 0);
    }

    public function test_copy_brings_over_only_missing_targets_from_the_previous_month(): void
    {
        $rizwan = StaffProfile::factory()->create(['tenant_id' => $this->tenant->id]);
        $azam = StaffProfile::factory()->create(['tenant_id' => $this->tenant->id]);
        StaffTarget::factory()->create(['tenant_id' => $this->tenant->id, 'staff_profile_id' => $rizwan->id, 'month' => '2026-05-01', 'target_amount' => 200000]);
        StaffTarget::factory()->create(['tenant_id' => $this->tenant->id, 'staff_profile_id' => $azam->id, 'month' => '2026-05-01', 'target_amount' => 150000]);
        StaffTarget::factory()->create(['tenant_id' => $this->tenant->id, 'staff_profile_id' => $rizwan->id, 'month' => '2026-06-01', 'target_amount' => 250000]);

        $response = $this->actingAs($this->owner)->postToTenant('/incentive-targets/copy', ['from_month' => '2026-05', 'to_month' => '2026-06']);

        $response->assertRedirect();
        $this->assertDatabaseHas('staff_targets', ['staff_profile_id' => $rizwan->id, 'month' => '2026-06-01', 'target_amount' => 250000]);
        $this->assertDatabaseHas('staff_targets', ['staff_profile_id' => $azam->id, 'month' => '2026-06-01', 'target_amount' => 150000]);
    }

    public function test_month_is_required_when_saving_targets(): void
    {
        $response = $this->actingAs($this->owner)->postToTenant('/incentive-targets', ['targets' => []]);

        $response->assertSessionHasErrors(['month', 'targets']);
    }
}
