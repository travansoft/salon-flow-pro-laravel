<?php

namespace Tests\Feature\Reports;

use App\Models\StaffProfile;
use App\Models\StaffTarget;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsTenant;
use Tests\Concerns\CreatesIncentiveBills;
use Tests\TestCase;

class TargetTrackerTest extends TestCase
{
    use ActsAsTenant, CreatesIncentiveBills, RefreshDatabase;

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
        $this->setUpBranch();
        $this->assignToBranch($owner);

        return $owner;
    }

    public function test_owner_can_view_tracker_for_chosen_month(): void
    {
        $owner = $this->owner();
        $this->useTenantAndBranchContext();
        $staff = StaffProfile::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Rizwan']);
        StaffTarget::factory()->create(['tenant_id' => $this->tenant->id, 'staff_profile_id' => $staff->id, 'month' => '2026-06-01', 'target_amount' => 3000]);
        $this->billWithLine('1000', $staff);

        $response = $this->actingAs($owner)->getFromTenant('/reports/target-tracker?month=2026-06');

        $response->assertOk();
        $response->assertViewIs('admin.reports.targetTracker');
        $response->assertSee('June 2026');
        $response->assertSee('Rizwan');
        $response->assertSee('3,000.00');
        $response->assertSee('Week 1');
    }

    public function test_staff_drill_down_shows_that_staff_member(): void
    {
        $owner = $this->owner();
        $this->useTenantAndBranchContext();
        $staff = StaffProfile::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Rizwan']);

        $response = $this->actingAs($owner)->getFromTenant("/reports/target-tracker?month=2026-06&staff={$staff->id}");

        $response->assertOk();
        $response->assertSee('Day-wise: Rizwan');
    }

    public function test_defaults_to_current_month_and_rejects_invalid_month(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)->getFromTenant('/reports/target-tracker')->assertOk()->assertSee(now()->format('F Y'));
        $this->actingAs($owner)->getFromTenant('/reports/target-tracker?month=garbage')->assertSessionHasErrors('month');
    }

    public function test_tracker_is_listed_in_the_reports_menu(): void
    {
        $response = $this->actingAs($this->owner())->getFromTenant('/reports/target-tracker');

        $response->assertSee('Target tracker');
    }

    public function test_owner_can_export_tracker_as_excel(): void
    {
        $response = $this->actingAs($this->owner())->getFromTenant('/reports/target-tracker/export?month=2026-06');

        $response->assertOk();
        $response->assertDownload('target-tracker-2026-06.xlsx');
    }

    public function test_user_without_permission_cannot_view_or_export(): void
    {
        $user = User::factory()->for($this->tenant)->create();

        $this->actingAs($user)->getFromTenant('/reports/target-tracker')->assertForbidden();
        $this->actingAs($user)->getFromTenant('/reports/target-tracker/export')->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->getFromTenant('/reports/target-tracker')->assertRedirect('/login');
        $this->getFromTenant('/reports/target-tracker/export')->assertRedirect('/login');
    }
}
