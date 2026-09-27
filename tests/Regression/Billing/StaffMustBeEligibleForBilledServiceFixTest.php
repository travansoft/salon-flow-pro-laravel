<?php

namespace Tests\Regression\Billing;

use App\Models\Client;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsTenant;
use Tests\TestCase;

class StaffMustBeEligibleForBilledServiceFixTest extends TestCase
{
    use ActsAsTenant, RefreshDatabase;

    /**
     * Bug: before staff-service mapping was enforced at the billing layer, a bill
     * line item could record any staff_profile_id regardless of whether that staff
     * member was actually mapped to perform the service, breaking commission
     * attribution. SettleQuickBillRequest now validates that the staff member is
     * mapped to the service (via the staff_service pivot) before the bill is created.
     */
    public function test_manual_bill_rejects_a_staff_member_not_mapped_to_the_service(): void
    {
        $this->setUpTenant();
        $this->seed(PermissionSeeder::class);

        $frontDesk = User::factory()->for($this->tenant)->create();
        $frontDesk->assignRole('FrontDesk');
        $client = Client::factory()->create(['tenant_id' => $this->tenant->id]);
        $service = Service::factory()->create(['tenant_id' => $this->tenant->id, 'price' => 500]);
        $unmappedStaffProfile = StaffProfile::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($frontDesk)->postJson($this->tenantUrl('/bills/settle'), [
            'client_id' => $client->id,
            'items' => [
                [
                    'description' => $service->name,
                    'service_id' => $service->id,
                    'staff_profile_id' => $unmappedStaffProfile->id,
                    'unit_price' => 500,
                ],
            ],
            'payment_method' => 'upi',
        ]);

        $response->assertJsonValidationErrors('items.0.staff_profile_id');
        $this->assertDatabaseMissing('bill_line_items', ['staff_profile_id' => $unmappedStaffProfile->id]);
    }
}
