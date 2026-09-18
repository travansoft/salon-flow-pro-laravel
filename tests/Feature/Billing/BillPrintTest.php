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

    public function test_print_receipt_shows_invoice_summary_and_amount_in_words(): void
    {
        $frontDesk = User::factory()->for($this->tenant)->create();
        $frontDesk->assignRole('FrontDesk');

        app(TenantContext::class)->set($this->tenant);
        $client = Client::factory()->create(['tenant_id' => $this->tenant->id]);
        $bill = app(BillingService::class)->createManualBill($client->id, $frontDesk->id, [
            ['description' => 'Haircut', 'unit_price' => 200, 'tax_rate' => 18],
        ]);

        $response = $this->actingAs($frontDesk)->getFromTenant("/bills/{$bill->id}/print");

        $response->assertOk();
        $response->assertSee('INVOICE');
        $response->assertSee('SUMMARY');
        $response->assertSee('Bill Amount');
        $response->assertSee('Two Hundred Thirty Six Rupees Only');
    }

    public function test_print_receipt_shows_a_per_rate_breakdown_only_when_rates_differ(): void
    {
        $frontDesk = User::factory()->for($this->tenant)->create();
        $frontDesk->assignRole('FrontDesk');

        app(TenantContext::class)->set($this->tenant);
        $client = Client::factory()->create(['tenant_id' => $this->tenant->id]);
        $singleRateBill = app(BillingService::class)->createManualBill($client->id, $frontDesk->id, [
            ['description' => 'Haircut', 'unit_price' => 500, 'tax_rate' => 18],
        ]);
        $mixedRateBill = app(BillingService::class)->createManualBill($client->id, $frontDesk->id, [
            ['description' => 'Hair Color', 'unit_price' => 100, 'tax_rate' => 5],
            ['description' => 'Spa Package', 'unit_price' => 200, 'tax_rate' => 18],
        ]);

        $singleRateResponse = $this->actingAs($frontDesk)->getFromTenant("/bills/{$singleRateBill->id}/print");
        $mixedRateResponse = $this->actingAs($frontDesk)->getFromTenant("/bills/{$mixedRateBill->id}/print");

        $singleRateResponse->assertOk();
        $singleRateResponse->assertDontSee('Taxable</span>', false);

        $mixedRateResponse->assertOk();
        $mixedRateResponse->assertSee('5.00% GST');
        $mixedRateResponse->assertSee('18.00% GST');
        $mixedRateResponse->assertSee('Taxable</span>', false);
    }

    public function test_print_receipt_shows_who_billed_and_when(): void
    {
        $frontDesk = User::factory()->for($this->tenant)->create(['name' => 'Meera Pillai']);
        $frontDesk->assignRole('FrontDesk');

        app(TenantContext::class)->set($this->tenant);
        $client = Client::factory()->create(['tenant_id' => $this->tenant->id]);
        $bill = app(BillingService::class)->createManualBill($client->id, $frontDesk->id, [
            ['description' => 'Haircut', 'unit_price' => 500],
        ]);

        $response = $this->actingAs($frontDesk)->getFromTenant("/bills/{$bill->id}/print");

        $response->assertOk();
        $response->assertSee('Meera Pillai');
        $response->assertSee($bill->created_at->format('d-M-Y H:i'));
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
