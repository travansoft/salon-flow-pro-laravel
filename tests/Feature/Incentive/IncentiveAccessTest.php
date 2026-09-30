<?php

namespace Tests\Feature\Incentive;

use App\Models\IncentiveSlab;
use App\Models\StaffProfile;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsTenant;
use Tests\TestCase;

class IncentiveAccessTest extends TestCase
{
    use ActsAsTenant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenant();
        $this->seed(PermissionSeeder::class);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->for($this->tenant)->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_owner_can_access_every_incentive_page(): void
    {
        $owner = $this->userWithRole('Owner');

        $this->actingAs($owner)->getFromTenant('/incentive-progress')->assertOk();
        $this->actingAs($owner)->getFromTenant('/incentive-targets')->assertOk();
        $this->actingAs($owner)->getFromTenant('/incentive-settings')->assertOk();
        $this->actingAs($owner)->getFromTenant('/staff-bonuses/create')->assertOk();
    }

    public function test_manager_can_access_every_incentive_page(): void
    {
        $manager = $this->userWithRole('Manager');

        $this->actingAs($manager)->getFromTenant('/incentive-progress')->assertOk();
        $this->actingAs($manager)->getFromTenant('/incentive-targets')->assertOk();
        $this->actingAs($manager)->getFromTenant('/incentive-settings')->assertOk();
    }

    public function test_stylist_can_view_progress_but_is_forbidden_from_management_pages(): void
    {
        $stylist = $this->userWithRole('Stylist');
        StaffProfile::factory()->create(['tenant_id' => $this->tenant->id, 'user_id' => $stylist->id]);

        $this->actingAs($stylist)->getFromTenant('/incentive-progress')->assertOk();
        $this->actingAs($stylist)->getFromTenant('/incentive-targets')->assertForbidden();
        $this->actingAs($stylist)->getFromTenant('/incentive-settings')->assertForbidden();
        $this->actingAs($stylist)->getFromTenant('/staff-bonuses/create')->assertForbidden();
    }

    public function test_stylist_cannot_change_settings_or_targets(): void
    {
        $stylist = $this->userWithRole('Stylist');
        $staff = StaffProfile::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAs($stylist)->putToTenant('/incentive-settings', [
            'servicing_share_percent' => 50,
            'referring_share_percent' => 50,
        ])->assertForbidden();
        $this->actingAs($stylist)->postToTenant('/incentive-targets', [
            'month' => '2026-06',
            'targets' => [$staff->id => 1000],
        ])->assertForbidden();
    }

    public function test_editor_without_delete_permission_cannot_remove_a_slab(): void
    {
        $stylist = $this->userWithRole('Stylist');
        $slab = IncentiveSlab::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAs($stylist)->deleteFromTenant("/incentive-slabs/{$slab->id}")->assertForbidden();

        $this->assertDatabaseHas('incentive_slabs', ['id' => $slab->id]);
    }

    public function test_front_desk_is_forbidden_from_all_incentive_routes(): void
    {
        $frontDesk = $this->userWithRole('FrontDesk');

        $this->actingAs($frontDesk)->getFromTenant('/incentive-progress')->assertForbidden();
        $this->actingAs($frontDesk)->getFromTenant('/incentive-targets')->assertForbidden();
        $this->actingAs($frontDesk)->getFromTenant('/incentive-settings')->assertForbidden();
        $this->actingAs($frontDesk)->getFromTenant('/staff-bonuses/create')->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->getFromTenant('/incentive-progress')->assertRedirect('/login');
    }
}
