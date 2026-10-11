<?php

namespace Tests\Regression\Inventory;

use App\Models\Client;
use App\Models\Product;
use App\Models\Service;
use App\Models\ServiceProductUsage;
use App\Models\User;
use App\Services\BillingService;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsTenant;
use Tests\Concerns\CreatesIncentiveBills;
use Tests\TestCase;

class VoidedBillStockFixTest extends TestCase
{
    use ActsAsTenant, CreatesIncentiveBills, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenant();
        $this->useTenantAndBranchContext();
    }

    public function test_voiding_a_bill_no_longer_leaves_stock_deducted(): void
    {
        $user = User::factory()->for($this->tenant)->create();
        $client = Client::factory()->create(['tenant_id' => $this->tenant->id]);
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id, 'quantity_on_hand' => 30]);
        $service = Service::factory()->create(['tenant_id' => $this->tenant->id]);
        ServiceProductUsage::factory()->create(['tenant_id' => $this->tenant->id, 'service_id' => $service->id, 'product_id' => $product->id, 'quantity_used' => 5]);
        $billing = app(BillingService::class);
        $bill = $billing->createManualBill($client->id, $user->id, [
            ['description' => $service->name, 'service_id' => $service->id, 'unit_price' => 400],
        ]);

        $billing->cancel($bill, $user->id);

        $this->assertSame('30.00', $product->fresh()->quantity_on_hand);
    }

    public function test_cancelling_a_bill_twice_does_not_restore_stock_twice(): void
    {
        $user = User::factory()->for($this->tenant)->create();
        $client = Client::factory()->create(['tenant_id' => $this->tenant->id]);
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id, 'quantity_on_hand' => 30]);
        $service = Service::factory()->create(['tenant_id' => $this->tenant->id]);
        ServiceProductUsage::factory()->create(['tenant_id' => $this->tenant->id, 'service_id' => $service->id, 'product_id' => $product->id, 'quantity_used' => 5]);
        $billing = app(BillingService::class);
        $bill = $billing->createManualBill($client->id, $user->id, [
            ['description' => $service->name, 'service_id' => $service->id, 'unit_price' => 400],
        ]);

        $billing->cancel($bill, $user->id);
        $billing->cancel($bill->fresh(), $user->id);

        $this->assertSame('30.00', $product->fresh()->quantity_on_hand);
    }

    public function test_count_above_system_quantity_is_recorded_as_correction_not_purchase(): void
    {
        $user = User::factory()->for($this->tenant)->create();
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id, 'quantity_on_hand' => 2]);

        app(InventoryService::class)->recordCount($product, 9, $user->id);

        $this->assertDatabaseCount('stock_purchases', 0);
        $this->assertDatabaseMissing('stock_adjustments', ['product_id' => $product->id, 'type' => 'purchase']);
        $this->assertDatabaseHas('stock_adjustments', ['product_id' => $product->id, 'type' => 'count_correction', 'quantity_delta' => 7]);
    }
}
