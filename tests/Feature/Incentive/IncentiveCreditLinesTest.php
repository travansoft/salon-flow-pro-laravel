<?php

namespace Tests\Feature\Incentive;

use App\Models\StaffProfile;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsTenant;
use Tests\Concerns\CreatesIncentiveBills;
use Tests\TestCase;

class IncentiveCreditLinesTest extends TestCase
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

    public function test_owner_sees_the_servicing_lines_behind_a_staff_row(): void
    {
        $owner = $this->userWithRole('Owner');
        $servicing = StaffProfile::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Rizwan Khan']);
        $referrer = StaffProfile::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Azam Ali']);
        $bill = $this->billWithLine('10000', $servicing, $referrer);

        $response = $this->actingAs($owner)->getFromTenant("/incentive-progress/{$servicing->id}?month=2026-06");

        $response->assertOk();
        $response->assertSee($bill->invoiceNumber());
        $response->assertSee('Servicing 70%');
        $response->assertSee('Azam Ali');
        $response->assertSee('7,000.00');
    }

    public function test_referring_staff_see_their_referral_lines(): void
    {
        $owner = $this->userWithRole('Owner');
        $servicing = StaffProfile::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Rizwan Khan']);
        $referrer = StaffProfile::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Azam Ali']);
        $this->billWithLine('10000', $servicing, $referrer);

        $response = $this->actingAs($owner)->getFromTenant("/incentive-progress/{$referrer->id}?month=2026-06");

        $response->assertSee('Referral 30%');
        $response->assertSee('3,000.00');
        $response->assertSee('Rizwan Khan');
    }

    public function test_lines_outside_the_month_and_unpaid_bills_are_not_listed(): void
    {
        $owner = $this->userWithRole('Owner');
        $staff = StaffProfile::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->billWithLine('11111', $staff, createdAt: '2026-05-10');
        $this->billWithLine('22222', $staff, status: 'unpaid');

        $response = $this->actingAs($owner)->getFromTenant("/incentive-progress/{$staff->id}?month=2026-06");

        $response->assertDontSee('11,111.00');
        $response->assertDontSee('22,222.00');
        $response->assertSee('No credited bills for this month.');
    }

    public function test_refunded_bill_line_shows_the_reduced_credit_and_a_note(): void
    {
        $owner = $this->userWithRole('Owner');
        $staff = StaffProfile::factory()->create(['tenant_id' => $this->tenant->id]);
        $bill = $this->billWithLine('10000', $staff);
        $bill->update(['amount_refunded' => 295]);

        $response = $this->actingAs($owner)->getFromTenant("/incentive-progress/{$staff->id}?month=2026-06");

        $response->assertSee('5,000.00');
        $response->assertSee('Refunded');
    }

    public function test_stylist_can_open_their_own_lines(): void
    {
        $stylist = $this->userWithRole('Stylist');
        $staff = StaffProfile::factory()->create(['tenant_id' => $this->tenant->id, 'user_id' => $stylist->id]);

        $this->actingAs($stylist)->getFromTenant("/incentive-progress/{$staff->id}")->assertOk();
    }

    public function test_stylist_cannot_open_another_staff_members_lines(): void
    {
        $stylist = $this->userWithRole('Stylist');
        StaffProfile::factory()->create(['tenant_id' => $this->tenant->id, 'user_id' => $stylist->id]);
        $other = StaffProfile::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAs($stylist)->getFromTenant("/incentive-progress/{$other->id}")->assertForbidden();
    }

    public function test_front_desk_is_forbidden_from_credit_lines(): void
    {
        $frontDesk = $this->userWithRole('FrontDesk');
        $staff = StaffProfile::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAs($frontDesk)->getFromTenant("/incentive-progress/{$staff->id}")->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $staff = StaffProfile::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->getFromTenant("/incentive-progress/{$staff->id}")->assertRedirect('/login');
    }

    public function test_staff_from_another_tenant_is_not_found(): void
    {
        $owner = $this->userWithRole('Owner');
        $otherStaff = StaffProfile::factory()->create();

        $this->actingAs($owner)->getFromTenant("/incentive-progress/{$otherStaff->id}")->assertNotFound();
    }

    public function test_progress_page_links_each_staff_name_to_their_lines(): void
    {
        $owner = $this->userWithRole('Owner');
        $staff = StaffProfile::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($owner)->getFromTenant('/incentive-progress?month=2026-06');

        $response->assertSee("/incentive-progress/{$staff->id}?month=2026-06", false);
    }
}
