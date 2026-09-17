<?php

namespace Tests\Feature\Billing;

use App\Models\Client;
use App\Models\User;
use App\Services\BillingService;
use App\Services\TenantContext;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsTenant;
use Tests\TestCase;

class BillPrintTest extends TestCase
{
    use ActsAsTenant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenant();
        $this->seed(PermissionSeeder::class);
    }

    public function test_front_desk_can_view_the_print_receipt(): void
    {
        $frontDesk = User::factory()->for($this->tenant)->create();
        $frontDesk->assignRole('FrontDesk');

        app(TenantContext::class)->set($this->tenant);
        $client = Client::factory()->create(['tenant_id' => $this->tenant->id]);
        $bill = app(BillingService::class)->createManualBill($client->id, $frontDesk->id, [
            ['description' => 'Haircut', 'unit_price' => 500],
        ]);

        $response = $this->actingAs($frontDesk)->getFromTenant("/bills/{$bill->id}/print");

        $response->assertOk();
        $response->assertSee($bill->invoiceNumber());
    }

    public function test_stylist_cannot_view_the_print_receipt(): void
    {
        $stylist = User::factory()->for($this->tenant)->create();
        $stylist->assignRole('Stylist');

        app(TenantContext::class)->set($this->tenant);
        $client = Client::factory()->create(['tenant_id' => $this->tenant->id]);
        $owner = User::factory()->for($this->tenant)->create();
        $bill = app(BillingService::class)->createManualBill($client->id, $owner->id, [
            ['description' => 'Haircut', 'unit_price' => 500],
        ]);

        $response = $this->actingAs($stylist)->getFromTenant("/bills/{$bill->id}/print");

        $response->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        app(TenantContext::class)->set($this->tenant);
        $client = Client::factory()->create(['tenant_id' => $this->tenant->id]);
        $owner = User::factory()->for($this->tenant)->create();
        $bill = app(BillingService::class)->createManualBill($client->id, $owner->id, [
            ['description' => 'Haircut', 'unit_price' => 500],
        ]);

        $response = $this->getFromTenant("/bills/{$bill->id}/print");

        $response->assertRedirect('/login');
    }
}
