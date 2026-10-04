<?php

namespace Tests\Regression\Billing;

use App\Models\Client;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsTenant;
use Tests\Concerns\CreatesEligibleStaff;
use Tests\TestCase;

class ServicingStaffOptionalOnNewBillFixTest extends TestCase
{
    use ActsAsTenant, CreatesEligibleStaff, RefreshDatabase;

    private Service $service;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenant();
        $this->seed(PermissionSeeder::class);

        $this->service = Service::factory()->create(['tenant_id' => $this->tenant->id, 'price' => 500]);
        $this->client = Client::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    /**
     * Change: servicing staff is no longer mandatory when billing; the bill
     * is flagged as incomplete instead.
     */
    public function test_a_bill_can_be_settled_without_the_servicing_staff(): void
    {
        $frontDesk = User::factory()->for($this->tenant)->create();
        $frontDesk->assignRole('FrontDesk');

        $response = $this->actingAs($frontDesk)->postJson($this->tenantUrl('/bills/settle'), [
            'client_id' => $this->client->id,
            'items' => [['service_id' => $this->service->id]],
            'payment_method' => 'cash',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('bill_line_items', ['bill_id' => $response->json('bill_id'), 'staff_profile_id' => null]);
    }

    public function test_a_backdated_bill_can_be_saved_without_the_servicing_staff(): void
    {
        $owner = User::factory()->for($this->tenant)->create();
        $owner->assignRole('Owner');

        $response = $this->actingAs($owner)->postJson($this->tenantUrl('/bills/backfill'), [
            'bill_date' => '2026-01-10',
            'client_id' => $this->client->id,
            'items' => [['description' => 'Haircut', 'service_id' => $this->service->id, 'unit_price' => 500]],
            'payment_method' => 'cash',
        ]);

        $response->assertOk();
        $this->assertDatabaseCount('bills', 1);
    }

    public function test_a_bill_with_staff_on_only_some_service_lines_is_accepted(): void
    {
        $frontDesk = User::factory()->for($this->tenant)->create();
        $frontDesk->assignRole('FrontDesk');
        $secondService = Service::factory()->create(['tenant_id' => $this->tenant->id, 'price' => 300]);

        $response = $this->actingAs($frontDesk)->postJson($this->tenantUrl('/bills/settle'), [
            'client_id' => $this->client->id,
            'items' => [
                ['service_id' => $this->service->id, 'staff_profile_id' => $this->eligibleStaffFor($this->service)->id],
                ['service_id' => $secondService->id],
            ],
            'payment_method' => 'cash',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('bill_line_items', ['bill_id' => $response->json('bill_id'), 'service_id' => $secondService->id, 'staff_profile_id' => null]);
    }

    public function test_a_bill_with_servicing_staff_on_every_service_line_is_accepted(): void
    {
        $frontDesk = User::factory()->for($this->tenant)->create();
        $frontDesk->assignRole('FrontDesk');

        $response = $this->actingAs($frontDesk)->postJson($this->tenantUrl('/bills/settle'), [
            'client_id' => $this->client->id,
            'items' => [['service_id' => $this->service->id, 'staff_profile_id' => $this->eligibleStaffFor($this->service)->id]],
            'payment_method' => 'cash',
        ]);

        $response->assertOk();
    }
}
