<?php

namespace Tests\Regression\Incentive;

use App\Models\StaffProfile;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsTenant;
use Tests\Concerns\CreatesIncentiveBills;
use Tests\TestCase;

class PrintedBillNeverShowsIncentiveSplitFixTest extends TestCase
{
    use ActsAsTenant, CreatesIncentiveBills, RefreshDatabase;

    public function test_customer_facing_print_never_shows_the_incentive_split_or_staff_names(): void
    {
        $this->setUpTenant();
        $this->seed(PermissionSeeder::class);
        $this->useTenantAndBranchContext();
        $owner = User::factory()->for($this->tenant)->create();
        $owner->assignRole('Owner');
        $this->assignToBranch($owner);
        $servicing = StaffProfile::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Rizwan Khan']);
        $referrer = StaffProfile::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Azam Ali']);
        $bill = $this->billWithLine('1000', $servicing, $referrer);

        $response = $this->actingAs($owner)->getFromTenant("/bills/{$bill->id}/print");

        $response->assertOk();
        $response->assertDontSee('Incentive split');
        $response->assertDontSee('referring 30%');
        $response->assertDontSee('Azam Ali');
    }
}
