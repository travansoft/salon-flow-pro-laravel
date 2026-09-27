<?php

namespace Tests\Regression\Billing;

use App\Models\Client;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsTenant;
use Tests\TestCase;

class QuickBillSettleHonoursEditedServicePriceFixTest extends TestCase
{
    use ActsAsTenant, RefreshDatabase;

    /**
     * Bug: QuickBillService::createAndSettle() always priced catalogued service
     * line items from Service::$price, ignoring any unit_price the billing staff
     * had edited on the new bill page. "Create bill" (createManualBill directly)
     * honoured the edit; "Create & settle" silently discarded it and billed the
     * original catalogue price instead.
     */
    public function test_create_and_settle_uses_the_edited_unit_price_not_the_catalogue_price(): void
    {
        $this->setUpTenant();
        $this->seed(PermissionSeeder::class);

        $frontDesk = User::factory()->for($this->tenant)->create();
        $frontDesk->assignRole('FrontDesk');
        $client = Client::factory()->create(['tenant_id' => $this->tenant->id]);
        $service = Service::factory()->create(['tenant_id' => $this->tenant->id, 'price' => 500, 'tax_rate' => 18]);

        $response = $this->actingAs($frontDesk)->postToTenant('/bills/settle', [
            'client_id' => $client->id,
            'items' => [
                [
                    'service_id' => $service->id,
                    'unit_price' => 350,
                ],
            ],
            'payment_method' => 'cash',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('bill_line_items', [
            'service_id' => $service->id,
            'unit_price' => 296.61,
        ]);
        $this->assertDatabaseMissing('bill_line_items', [
            'service_id' => $service->id,
            'unit_price' => 423.73,
        ]);
    }
}
