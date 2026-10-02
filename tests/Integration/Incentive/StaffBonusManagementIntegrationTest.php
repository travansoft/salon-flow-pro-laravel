<?php

namespace Tests\Integration\Incentive;

use App\Models\StaffIncentive;
use App\Models\StaffProfile;
use App\Models\User;
use App\Services\IncentiveService;
use App\Services\TenantContext;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\ActsAsTenant;
use Tests\TestCase;

class StaffBonusManagementIntegrationTest extends TestCase
{
    use ActsAsTenant, RefreshDatabase;

    public function test_edited_and_deleted_bonuses_change_the_progress_bonus_total(): void
    {
        $this->setUpTenant();
        $this->seed(PermissionSeeder::class);
        $owner = User::factory()->for($this->tenant)->create();
        $owner->assignRole('Owner');
        $staff = StaffProfile::factory()->create(['tenant_id' => $this->tenant->id]);
        $kept = StaffIncentive::factory()->create(['tenant_id' => $this->tenant->id, 'staff_profile_id' => $staff->id, 'amount' => 500, 'awarded_date' => '2026-06-10']);
        $removed = StaffIncentive::factory()->create(['tenant_id' => $this->tenant->id, 'staff_profile_id' => $staff->id, 'amount' => 300, 'awarded_date' => '2026-06-11']);

        $this->actingAs($owner)->putToTenant("/staff-bonuses/{$kept->id}", [
            'staff_profile_id' => $staff->id,
            'amount' => 800,
            'reason' => 'Raised',
            'awarded_date' => '2026-06-10',
        ]);
        $this->actingAs($owner)->deleteFromTenant("/staff-bonuses/{$removed->id}");

        app(TenantContext::class)->set($this->tenant);
        $progress = app(IncentiveService::class)->progressForAll(Carbon::parse('2026-06-01'))->get($staff->id);

        $this->assertSame('800.00', $progress['bonus']);
    }
}
