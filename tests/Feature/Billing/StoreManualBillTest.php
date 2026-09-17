<?php

namespace Tests\Feature\Billing;

use App\Models\Client;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsTenant;
use Tests\TestCase;

class StoreManualBillTest extends TestCase
{
    use ActsAsTenant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenant();
        $this->seed(PermissionSeeder::class);
    }

    public function test_front_desk_can_create_a_manual_bill_with_an_eligible_staff_member(): void
    {
        $frontDesk = User::factory()->for($this->tenant)->create();
        $frontDesk->assignRole('FrontDesk');
        $client = Client::factory()->create(['tenant_id' => $this->tenant->id]);
        $service = Service::factory()->create(['tenant_id' => $this->tenant->id, 'price' => 500]);
        $staffProfile = StaffProfile::factory()->create(['tenant_id' => $this->tenant->id]);
        $service->staff()->sync([$staffProfile->id]);

        $response = $this->actingAs($frontDesk)->postToTenant('/bills', [
            'client_id' => $client->id,
            'items' => [
                [
                    'description' => $service->name,
                    'service_id' => $service->id,
                    'staff_profile_id' => $staffProfile->id,
                    'unit_price' => 500,
                ],
            ],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('bill_line_items', [
            'service_id' => $service->id,
            'staff_profile_id' => $staffProfile->id,
        ]);
    }

    public function test_validation_rejects_a_staff_member_not_eligible_for_the_service(): void
    {
        $frontDesk = User::factory()->for($this->tenant)->create();
        $frontDesk->assignRole('FrontDesk');
        $client = Client::factory()->create(['tenant_id' => $this->tenant->id]);
        $service = Service::factory()->create(['tenant_id' => $this->tenant->id, 'price' => 500]);
        $ineligibleStaffProfile = StaffProfile::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($frontDesk)->postToTenant('/bills', [
            'client_id' => $client->id,
            'items' => [
                [
                    'description' => $service->name,
                    'service_id' => $service->id,
                    'staff_profile_id' => $ineligibleStaffProfile->id,
                    'unit_price' => 500,
                ],
            ],
        ]);

        $response->assertSessionHasErrors('items.0.staff_profile_id');
    }

    public function test_manual_line_item_without_a_service_does_not_require_staff(): void
    {
        $frontDesk = User::factory()->for($this->tenant)->create();
        $frontDesk->assignRole('FrontDesk');
        $client = Client::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($frontDesk)->postToTenant('/bills', [
            'client_id' => $client->id,
            'items' => [
                ['description' => 'Retail shampoo', 'unit_price' => 350],
            ],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('bill_line_items', [
            'description' => 'Retail shampoo',
            'staff_profile_id' => null,
        ]);
    }

    public function test_leaving_every_client_field_blank_bills_the_walk_in_customer(): void
    {
        $frontDesk = User::factory()->for($this->tenant)->create();
        $frontDesk->assignRole('FrontDesk');

        $response = $this->actingAs($frontDesk)->postToTenant('/bills', [
            'items' => [
                ['description' => 'Retail shampoo', 'unit_price' => 350],
            ],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('clients', [
            'tenant_id' => $this->tenant->id,
            'name' => 'Walk-in customer',
        ]);
    }

    public function test_typing_new_client_details_creates_a_client_and_bills_them(): void
    {
        $frontDesk = User::factory()->for($this->tenant)->create();
        $frontDesk->assignRole('FrontDesk');

        $response = $this->actingAs($frontDesk)->postToTenant('/bills', [
            'client_name' => 'Priya Nair',
            'client_phone' => '9876543210',
            'client_gst_number' => '32AAAAA0000A1Z5',
            'items' => [
                ['description' => 'Retail shampoo', 'unit_price' => 350],
            ],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('clients', [
            'tenant_id' => $this->tenant->id,
            'name' => 'Priya Nair',
            'phone' => '9876543210',
            'gst_number' => '32AAAAA0000A1Z5',
        ]);
    }

    public function test_typing_a_phone_that_matches_an_existing_client_reuses_it(): void
    {
        $frontDesk = User::factory()->for($this->tenant)->create();
        $frontDesk->assignRole('FrontDesk');
        $existing = Client::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Priya Nair', 'phone' => '9876543210']);

        $response = $this->actingAs($frontDesk)->postToTenant('/bills', [
            'client_name' => 'Priya Nair',
            'client_phone' => '9876543210',
            'items' => [
                ['description' => 'Retail shampoo', 'unit_price' => 350],
            ],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('bills', ['client_id' => $existing->id]);
        $this->assertSame(1, Client::query()->where('phone', '9876543210')->count());
    }
}
