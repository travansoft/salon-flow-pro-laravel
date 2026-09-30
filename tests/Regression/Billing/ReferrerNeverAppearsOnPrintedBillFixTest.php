<?php

namespace Tests\Regression\Billing;

use App\Models\Bill;
use App\Models\BillLineItem;
use App\Models\StaffProfile;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsTenant;
use Tests\TestCase;

class ReferrerNeverAppearsOnPrintedBillFixTest extends TestCase
{
    use ActsAsTenant, RefreshDatabase;

    /**
     * Referring staff is internal-only attribution and must stay off the customer receipt.
     */
    public function test_referring_staff_name_is_not_rendered_on_the_printed_bill(): void
    {
        $this->setUpTenant();
        $this->seed(PermissionSeeder::class);
        $user = User::factory()->for($this->tenant)->create();
        $user->assignRole('FrontDesk');
        $referrer = StaffProfile::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Secret Referrer']);
        $bill = Bill::factory()->create(['tenant_id' => $this->tenant->id]);
        BillLineItem::factory()->create([
            'tenant_id' => $this->tenant->id,
            'bill_id' => $bill->id,
            'referred_by_staff_profile_id' => $referrer->id,
        ]);

        $response = $this->actingAs($user)->get($this->tenantUrl("/bills/{$bill->id}/print"));

        $response->assertDontSee('Secret Referrer');
    }
}
