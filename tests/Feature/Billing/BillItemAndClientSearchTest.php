<?php

namespace Tests\Feature\Billing;

use App\Models\Client;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsTenant;
use Tests\TestCase;

class BillItemAndClientSearchTest extends TestCase
{
    use ActsAsTenant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenant();
        $this->seed(PermissionSeeder::class);
    }

    public function test_service_search_matches_by_partial_name(): void
    {
        $user = User::factory()->for($this->tenant)->create();
        $user->assignRole('FrontDesk');
        Service::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Classic Haircut']);
        Service::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Manicure']);

        $response = $this->actingAs($user)->getFromTenant('/services/search?q=hair');

        $response->assertOk()->assertJsonCount(1, 'services');
        $this->assertSame('Classic Haircut', $response->json('services.0.name'));
    }

    public function test_service_search_matches_by_exact_code(): void
    {
        $user = User::factory()->for($this->tenant)->create();
        $user->assignRole('FrontDesk');
        Service::factory()->create(['tenant_id' => $this->tenant->id, 'code' => '101', 'name' => 'Haircut']);

        $response = $this->actingAs($user)->getFromTenant('/services/search?q=101');

        $response->assertOk()->assertJsonCount(1, 'services');
    }

    public function test_service_search_returns_gst_inclusive_price_and_tax_rate(): void
    {
        $user = User::factory()->for($this->tenant)->create();
        $user->assignRole('FrontDesk');
        Service::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Gents Haircut', 'price' => 354, 'tax_rate' => null]);

        $response = $this->actingAs($user)->getFromTenant('/services/search?q=Haircut');

        $response->assertOk();
        $this->assertEquals(354.0, $response->json('services.0.price'));
        $this->assertEquals(18.0, $response->json('services.0.tax_rate'));
        $this->assertEquals(354.0, $response->json('services.0.price_inclusive'));
    }

    public function test_service_search_uses_the_services_own_tax_rate_when_set(): void
    {
        $user = User::factory()->for($this->tenant)->create();
        $user->assignRole('FrontDesk');
        Service::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Retail Shampoo', 'price' => 105, 'tax_rate' => 5]);

        $response = $this->actingAs($user)->getFromTenant('/services/search?q=Shampoo');

        $response->assertOk();
        $this->assertEquals(5.0, $response->json('services.0.tax_rate'));
        $this->assertEquals(105.0, $response->json('services.0.price_inclusive'));
    }

    public function test_service_search_flags_services_that_require_rate_confirmation(): void
    {
        $user = User::factory()->for($this->tenant)->create();
        $user->assignRole('FrontDesk');
        Service::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Bridal Facial', 'requires_rate_confirmation' => true]);
        Service::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Classic Haircut', 'requires_rate_confirmation' => false]);

        $response = $this->actingAs($user)->getFromTenant('/services/search?q=a');

        $response->assertOk();
        $services = collect($response->json('services'))->keyBy('name');
        $this->assertTrue($services['Bridal Facial']['requires_rate_confirmation']);
        $this->assertFalse($services['Classic Haircut']['requires_rate_confirmation']);
    }

    public function test_service_search_excludes_inactive_services(): void
    {
        $user = User::factory()->for($this->tenant)->create();
        $user->assignRole('FrontDesk');
        Service::factory()->inactive()->create(['tenant_id' => $this->tenant->id, 'name' => 'Retired facial']);

        $response = $this->actingAs($user)->getFromTenant('/services/search?q=facial');

        $response->assertOk()->assertJsonCount(0, 'services');
    }

    public function test_stylist_cannot_search_services_for_billing(): void
    {
        $stylist = User::factory()->for($this->tenant)->create();
        $stylist->assignRole('Stylist');

        $response = $this->actingAs($stylist)->getFromTenant('/services/search?q=hair');

        $response->assertForbidden();
    }

    public function test_client_search_matches_by_phone_or_name(): void
    {
        $user = User::factory()->for($this->tenant)->create();
        $user->assignRole('FrontDesk');
        Client::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Priya Nair', 'phone' => '9876543210']);
        Client::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Arjun Menon', 'phone' => '9000000001']);

        $response = $this->actingAs($user)->getFromTenant('/clients/search?q=9876');

        $response->assertOk()->assertJsonCount(1, 'clients');
        $this->assertSame('Priya Nair', $response->json('clients.0.name'));
    }

    public function test_client_search_includes_gst_number_for_autofill(): void
    {
        $user = User::factory()->for($this->tenant)->create();
        $user->assignRole('FrontDesk');
        Client::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Priya Nair', 'phone' => '9876543210', 'gst_number' => '32AAAAA0000A1Z5']);

        $response = $this->actingAs($user)->getFromTenant('/clients/search?q=Priya');

        $response->assertOk();
        $this->assertSame('32AAAAA0000A1Z5', $response->json('clients.0.gst_number'));
    }

    public function test_client_search_returns_empty_for_blank_query(): void
    {
        $user = User::factory()->for($this->tenant)->create();
        $user->assignRole('FrontDesk');

        $response = $this->actingAs($user)->getFromTenant('/clients/search?q=');

        $response->assertOk()->assertJsonCount(0, 'clients');
    }
}
