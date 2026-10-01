<?php

namespace Tests\Feature\Incentive;

use App\Models\Bill;
use App\Models\StaffProfile;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsTenant;
use Tests\Concerns\CreatesIncentiveBills;
use Tests\TestCase;

class BillIncentiveSplitTest extends TestCase
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

    public function test_owner_sees_the_servicing_and_referring_split_on_the_bill(): void
    {
        $owner = $this->userWithRole('Owner');
        $servicing = StaffProfile::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Rizwan Khan']);
        $referrer = StaffProfile::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Azam Ali']);
        $bill = $this->billWithLine('1000', $servicing, $referrer, gst: '180');

        $response = $this->actingAs($owner)->getFromTenant("/bills/{$bill->id}");

        $response->assertOk();
        $response->assertSee('Incentive split');
        $response->assertSee('servicing 70%');
        $response->assertSee('referring 30%');
        $response->assertSee('826.00');
        $response->assertSee('354.00');
    }

    public function test_direct_line_shows_the_full_value_to_the_servicing_staff(): void
    {
        $owner = $this->userWithRole('Owner');
        $servicing = StaffProfile::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Rizwan Khan']);
        $bill = $this->billWithLine('1000', $servicing);

        $response = $this->actingAs($owner)->getFromTenant("/bills/{$bill->id}");

        $response->assertSee('servicing 100%');
        $response->assertDontSee('referring');
    }

    public function test_split_is_hidden_from_users_without_incentive_access(): void
    {
        $frontDesk = $this->userWithRole('FrontDesk');
        $servicing = StaffProfile::factory()->create(['tenant_id' => $this->tenant->id]);
        $referrer = StaffProfile::factory()->create(['tenant_id' => $this->tenant->id]);
        $bill = $this->billWithLine('1000', $servicing, $referrer);

        $response = $this->actingAs($frontDesk)->getFromTenant("/bills/{$bill->id}");

        $response->assertOk();
        $response->assertDontSee('Incentive split');
    }

    public function test_cancelled_bill_split_is_marked_as_not_counted(): void
    {
        $owner = $this->userWithRole('Owner');
        $servicing = StaffProfile::factory()->create(['tenant_id' => $this->tenant->id]);
        $bill = $this->billWithLine('1000', $servicing, status: Bill::StatusVoid);

        $response = $this->actingAs($owner)->getFromTenant("/bills/{$bill->id}");

        $response->assertSee('not counted toward targets');
    }

    public function test_unpaid_bill_split_explains_when_it_starts_counting(): void
    {
        $owner = $this->userWithRole('Owner');
        $servicing = StaffProfile::factory()->create(['tenant_id' => $this->tenant->id]);
        $bill = $this->billWithLine('1000', $servicing, status: Bill::StatusUnpaid);

        $response = $this->actingAs($owner)->getFromTenant("/bills/{$bill->id}");

        $response->assertSee('once the bill is fully paid');
    }
}
