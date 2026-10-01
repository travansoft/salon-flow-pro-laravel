<?php

namespace Tests\Integration\Incentive;

use App\Models\IncentiveSetting;
use App\Models\IncentiveSlab;
use App\Models\StaffProfile;
use App\Models\StaffTarget;
use App\Models\Tenant;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IncentiveDatabaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_same_slab_threshold_can_exist_in_different_tenants(): void
    {
        $first = Tenant::factory()->create();
        $second = Tenant::factory()->create();

        IncentiveSlab::factory()->create(['tenant_id' => $first->id, 'min_achievement_percent' => 80]);
        IncentiveSlab::factory()->create(['tenant_id' => $second->id, 'min_achievement_percent' => 80]);

        $this->assertDatabaseCount('incentive_slabs', 2);
    }

    public function test_a_tenant_cannot_have_two_slabs_with_the_same_threshold(): void
    {
        $tenant = Tenant::factory()->create();
        IncentiveSlab::factory()->create(['tenant_id' => $tenant->id, 'min_achievement_percent' => 80]);

        $this->expectException(QueryException::class);

        IncentiveSlab::factory()->create(['tenant_id' => $tenant->id, 'min_achievement_percent' => 80]);
    }

    public function test_a_staff_member_cannot_have_two_targets_for_the_same_month(): void
    {
        $tenant = Tenant::factory()->create();
        $staff = StaffProfile::factory()->create(['tenant_id' => $tenant->id]);
        StaffTarget::factory()->create(['tenant_id' => $tenant->id, 'staff_profile_id' => $staff->id, 'month' => '2026-06-01']);

        $this->expectException(QueryException::class);

        StaffTarget::factory()->create(['tenant_id' => $tenant->id, 'staff_profile_id' => $staff->id, 'month' => '2026-06-01']);
    }

    public function test_a_tenant_can_only_have_one_settings_row(): void
    {
        $tenant = Tenant::factory()->create();
        IncentiveSetting::factory()->create(['tenant_id' => $tenant->id]);

        $this->expectException(QueryException::class);

        IncentiveSetting::factory()->create(['tenant_id' => $tenant->id]);
    }

    public function test_deleting_a_staff_member_removes_their_targets(): void
    {
        $tenant = Tenant::factory()->create();
        $staff = StaffProfile::factory()->create(['tenant_id' => $tenant->id]);
        $target = StaffTarget::factory()->create(['tenant_id' => $tenant->id, 'staff_profile_id' => $staff->id]);

        $staff->forceDelete();

        $this->assertDatabaseMissing('staff_targets', ['id' => $target->id]);
    }

    public function test_target_is_stored_as_a_plain_first_of_month_date(): void
    {
        $tenant = Tenant::factory()->create();
        $target = StaffTarget::factory()->create(['tenant_id' => $tenant->id, 'month' => '2026-06-01']);

        $this->assertDatabaseHas('staff_targets', ['id' => $target->id, 'month' => '2026-06-01']);
    }
}
