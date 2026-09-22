<?php

namespace Tests\Regression\Billing;

use App\Models\Client;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsTenant;
use Tests\TestCase;

class ManualBillHonoursCustomServiceTaxRateFixTest extends TestCase
{
    use ActsAsTenant, RefreshDatabase;

    /**
     * Bug: the bill page's autosuggest never sent items.*.tax_rate when adding a
     * service line item, so BillingService::createBill() always fell back to the
     * tenant's default GST rate, silently ignoring a service's own custom tax_rate.
     * A 5%-rated retail product was billed at the tenant default (18%) instead.
     * Fixed by sending the service's effective tax rate in the bill payload.
     */
    public function test_manual_bill_uses_the_services_own_tax_rate_not_the_tenant_default(): void
    {
        $this->setUpTenant();
        $this->tenant->update(['default_gst_rate' => 18]);
        $this->seed(PermissionSeeder::class);

        $frontDesk = User::factory()->for($this->tenant)->create();
        $frontDesk->assignRole('FrontDesk');
        $client = Client::factory()->create(['tenant_id' => $this->tenant->id]);
        $service = Service::factory()->create(['tenant_id' => $this->tenant->id, 'price' => 100, 'tax_rate' => 5]);

        $response = $this->actingAs($frontDesk)->postToTenant('/bills', [
            'client_id' => $client->id,
            'items' => [
                [
                    'description' => $service->name,
                    'service_id' => $service->id,
                    'unit_price' => 100,
                    'tax_rate' => 5,
                ],
            ],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('bill_line_items', [
            'service_id' => $service->id,
            'tax_rate' => 5,
        ]);
        $this->assertDatabaseHas('bills', [
            'client_id' => $client->id,
            'tax_amount' => 5,
        ]);
    }
}
