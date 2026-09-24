<?php

namespace Tests\Unit\Commission;

use App\Models\Branch;
use App\Models\StaffIncentive;
use App\Models\StaffProfile;
use App\Models\Tenant;
use App\Models\User;
use App\Repositories\Eloquent\StaffIncentiveRepository;
use App\Services\BranchContext;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class StaffIncentiveRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_persists_a_new_incentive(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);
        $staff = StaffProfile::factory()->create(['tenant_id' => $tenant->id]);
        $awardedBy = User::factory()->for($tenant)->create();

        $repository = app(StaffIncentiveRepository::class);

        $incentive = $repository->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'staff_profile_id' => $staff->id,
            'amount' => 500,
            'reason' => 'Client praise',
            'awarded_date' => '2026-06-10',
            'awarded_by' => $awardedBy->id,
        ]);

        $this->assertDatabaseHas('staff_incentives', [
            'id' => $incentive->id,
            'staff_profile_id' => $staff->id,
            'reason' => 'Client praise',
        ]);
    }

    public function test_get_for_staff_between_dates_only_returns_matching_staff_and_range(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);
        $staff = StaffProfile::factory()->create(['tenant_id' => $tenant->id]);
        $otherStaff = StaffProfile::factory()->create(['tenant_id' => $tenant->id]);

        StaffIncentive::factory()->create([
            'tenant_id' => $tenant->id,
            'staff_profile_id' => $staff->id,
            'awarded_date' => '2026-06-15',
        ]);
        StaffIncentive::factory()->create([
            'tenant_id' => $tenant->id,
            'staff_profile_id' => $staff->id,
            'awarded_date' => '2026-01-01',
        ]);
        StaffIncentive::factory()->create([
            'tenant_id' => $tenant->id,
            'staff_profile_id' => $otherStaff->id,
            'awarded_date' => '2026-06-15',
        ]);

        $repository = app(StaffIncentiveRepository::class);

        $results = $repository->getForStaffBetweenDates($staff, Carbon::parse('2026-06-01'), Carbon::parse('2026-06-30'));

        $this->assertCount(1, $results);
        $this->assertSame($staff->id, $results->first()->staff_profile_id);
    }
}
