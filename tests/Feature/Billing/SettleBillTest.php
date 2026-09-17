<?php

namespace Tests\Feature\Billing;

use App\Models\Client;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsTenant;
use Tests\TestCase;

class SettleBillTest extends TestCase
{
    use ActsAsTenant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenant();
        $this->seed(PermissionSeeder::class);
    }

    public function test_front_desk_can_open_the_new_bill_screen(): void
    {
        $frontDesk = User::factory()->for($this->tenant)->create();
        $frontDesk->assignRole('FrontDesk');

        $response = $this->actingAs($frontDesk)->getFromTenant('/bills/create');

        $response->assertOk();
    }

    public function test_stylist_cannot_open_the_new_bill_screen(): void
    {
        $stylist = User::factory()->for($this->tenant)->create();
        $stylist->assignRole('Stylist');

        $response = $this->actingAs($stylist)->getFromTenant('/bills/create');

        $response->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->getFromTenant('/bills/create');

        $response->assertRedirect($this->tenantUrl('/login'));
    }

    public function test_settle_creates_a_paid_bill_and_returns_redirect_url(): void
    {
        $user = User::factory()->for($this->tenant)->create();
        $user->assignRole('FrontDesk');
        $service = Service::factory()->create(['tenant_id' => $this->tenant->id, 'price' => 500]);
        $client = Client::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($user)->postToTenant('/bills/settle', [
            'client_id' => $client->id,
            'items' => [
                ['service_id' => $service->id, 'quantity' => 1],
            ],
            'payment_method' => 'cash',
        ]);

        $response->assertOk()->assertJsonStructure(['bill_id', 'bill_number', 'total', 'redirect']);
        $this->assertDatabaseHas('bills', ['id' => $response->json('bill_id'), 'status' => 'paid']);
    }

    public function test_settle_defaults_to_a_walk_in_client_when_none_given(): void
    {
        $user = User::factory()->for($this->tenant)->create();
        $user->assignRole('FrontDesk');
        $service = Service::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($user)->postToTenant('/bills/settle', [
            'items' => [
                ['service_id' => $service->id],
            ],
            'payment_method' => 'cash',
        ]);

        $response->assertOk();
    }

    public function test_settle_creates_a_client_from_typed_details_when_no_search_result_was_selected(): void
    {
        $user = User::factory()->for($this->tenant)->create();
        $user->assignRole('FrontDesk');
        $service = Service::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($user)->postToTenant('/bills/settle', [
            'client_name' => 'Priya Nair',
            'client_phone' => '9876543210',
            'items' => [
                ['service_id' => $service->id],
            ],
            'payment_method' => 'cash',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('clients', [
            'tenant_id' => $this->tenant->id,
            'name' => 'Priya Nair',
            'phone' => '9876543210',
        ]);
    }

    public function test_settle_validates_required_fields(): void
    {
        $user = User::factory()->for($this->tenant)->create();
        $user->assignRole('FrontDesk');

        $response = $this->actingAs($user)->postToTenant('/bills/settle', [
            'items' => [],
            'payment_method' => 'cash',
        ]);

        $response->assertSessionHasErrors('items');
    }

    public function test_stylist_cannot_settle_a_bill(): void
    {
        $stylist = User::factory()->for($this->tenant)->create();
        $stylist->assignRole('Stylist');
        $service = Service::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($stylist)->postToTenant('/bills/settle', [
            'items' => [
                ['service_id' => $service->id],
            ],
            'payment_method' => 'cash',
        ]);

        $response->assertForbidden();
    }
}
