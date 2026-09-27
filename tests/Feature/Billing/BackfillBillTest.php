<?php

namespace Tests\Feature\Billing;

use App\Models\Client;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsTenant;
use Tests\TestCase;

class BackfillBillTest extends TestCase
{
    use ActsAsTenant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenant();
        $this->seed(PermissionSeeder::class);
    }

    public function test_owner_can_view_the_backfill_form(): void
    {
        $owner = User::factory()->for($this->tenant)->create();
        $owner->assignRole('Owner');

        $response = $this->actingAs($owner)->getFromTenant('/bills/backfill');

        $response->assertOk();
    }

    public function test_front_desk_cannot_view_the_backfill_form(): void
    {
        $frontDesk = User::factory()->for($this->tenant)->create();
        $frontDesk->assignRole('FrontDesk');

        $response = $this->actingAs($frontDesk)->getFromTenant('/bills/backfill');

        $response->assertForbidden();
    }

    public function test_stylist_cannot_submit_a_backfilled_bill(): void
    {
        $stylist = User::factory()->for($this->tenant)->create();
        $stylist->assignRole('Stylist');

        $response = $this->actingAs($stylist)->postToTenant('/bills/backfill', [
            'bill_date' => '2026-01-10',
            'items' => [['description' => 'Haircut', 'unit_price' => 500]],
            'payment_method' => 'cash',
        ]);

        $response->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->getFromTenant('/bills/backfill');

        $response->assertRedirect($this->tenantUrl('/login'));
    }

    public function test_owner_can_create_a_backdated_bill_that_is_recorded_as_paid(): void
    {
        $owner = User::factory()->for($this->tenant)->create();
        $owner->assignRole('Owner');
        $client = Client::factory()->create(['tenant_id' => $this->tenant->id]);
        $service = Service::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($owner)->postJson($this->tenantUrl('/bills/backfill'), [
            'bill_date' => '2026-01-10',
            'client_id' => $client->id,
            'items' => [
                ['description' => 'Haircut', 'service_id' => $service->id, 'unit_price' => 500, 'tax_rate' => 18],
            ],
            'payment_method' => 'cash',
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['bill_id', 'bill_number', 'total', 'redirect']);

        $this->assertDatabaseHas('bills', [
            'client_id' => $client->id,
            'status' => 'paid',
        ]);
    }

    public function test_backfill_validation_rejects_a_future_bill_date(): void
    {
        $owner = User::factory()->for($this->tenant)->create();
        $owner->assignRole('Owner');
        $client = Client::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($owner)->postJson($this->tenantUrl('/bills/backfill'), [
            'bill_date' => now()->addDay()->toDateString(),
            'client_id' => $client->id,
            'items' => [
                ['description' => 'Haircut', 'unit_price' => 500],
            ],
            'payment_method' => 'cash',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('bill_date');
    }

    public function test_backfill_validation_requires_at_least_one_item(): void
    {
        $owner = User::factory()->for($this->tenant)->create();
        $owner->assignRole('Owner');

        $response = $this->actingAs($owner)->postJson($this->tenantUrl('/bills/backfill'), [
            'bill_date' => '2026-01-10',
            'items' => [],
            'payment_method' => 'cash',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('items');
    }
}
