<?php

namespace Tests\Feature\Incentive;

use App\Models\Bill;
use App\Models\StaffProfile;
use App\Models\StaffTarget;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsTenant;
use Tests\Concerns\CreatesIncentiveBills;
use Tests\TestCase;

class IncentiveProgressTest extends TestCase
{
    use ActsAsTenant, CreatesIncentiveBills, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenant();
        $this->seed(PermissionSeeder::class);
        $this->useTenantAndBranchContext();
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->for($this->tenant)->create();
        $user->assignRole($role);
        $this->assignToBranch($user);

        return $user;
    }

    public function test_owner_sees_target_achievement_and_incentive_for_each_staff_member(): void
    {
        $owner = $this->userWithRole('Owner');
        $staff = StaffProfile::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Rizwan Khan']);
        StaffTarget::factory()->create([
            'tenant_id' => $this->tenant->id,
            'staff_profile_id' => $staff->id,
            'month' => '2026-06-01',
            'target_amount' => 100000,
        ]);
        $this->billWithLine('100000', $staff);

        $response = $this->actingAs($owner)->getFromTenant('/incentive-progress?month=2026-06');

        $response->assertOk();
        $response->assertSee('Rizwan Khan');
        $response->assertSee('100.00%');
        $response->assertSee('5,000.00');
    }

    public function test_referral_credit_is_shown_for_the_referring_staff_member(): void
    {
        $owner = $this->userWithRole('Owner');
        $servicing = StaffProfile::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Rizwan Khan']);
        $referrer = StaffProfile::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Azam Ali']);
        $this->billWithLine('10000', $servicing, $referrer);

        $response = $this->actingAs($owner)->getFromTenant('/incentive-progress?month=2026-06');

        $response->assertOk();
        $response->assertSee('Referral &#8377;3,000.00', false);
        $response->assertSee('Own &#8377;7,000.00', false);
    }

    public function test_stylist_only_sees_their_own_row(): void
    {
        $stylist = $this->userWithRole('Stylist');
        StaffProfile::factory()->create(['tenant_id' => $this->tenant->id, 'user_id' => $stylist->id, 'name' => 'Stylist Own']);
        StaffProfile::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Someone Else']);

        $response = $this->actingAs($stylist)->getFromTenant('/incentive-progress');

        $response->assertOk();
        $response->assertSee('Stylist Own');
        $response->assertDontSee('Someone Else');
    }

    public function test_stylist_without_a_staff_profile_is_forbidden(): void
    {
        $stylist = $this->userWithRole('Stylist');

        $this->actingAs($stylist)->getFromTenant('/incentive-progress')->assertForbidden();
    }

    public function test_invalid_month_is_rejected(): void
    {
        $owner = $this->userWithRole('Owner');

        $response = $this->actingAs($owner)->getFromTenant('/incentive-progress?month=not-a-month');

        $response->assertSessionHasErrors('month');
    }

    public function test_unpaid_bills_do_not_show_credit(): void
    {
        $owner = $this->userWithRole('Owner');
        $staff = StaffProfile::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Rizwan Khan']);
        $this->billWithLine('50000', $staff, status: Bill::StatusUnpaid);

        $response = $this->actingAs($owner)->getFromTenant('/incentive-progress?month=2026-06');

        $response->assertDontSee('50,000.00');
    }
}
