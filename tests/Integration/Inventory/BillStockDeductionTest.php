<?php

namespace Tests\Integration\Inventory;

use App\Enums\StockMovementType;
use App\Models\Client;
use App\Models\Product;
use App\Models\Service;
use App\Models\ServiceProductUsage;
use App\Models\User;
use App\Services\BillingService;
use App\Services\InventoryService;
use App\Services\QuickBillService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsTenant;
use Tests\Concerns\CreatesCombos;
use Tests\Concerns\CreatesIncentiveBills;
use Tests\TestCase;

class BillStockDeductionTest extends TestCase
{
    use ActsAsTenant, CreatesCombos, CreatesIncentiveBills, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenant();
        $this->useTenantAndBranchContext();
    }

    private function link(Service $service, Product $product, float $quantity): void
    {
        ServiceProductUsage::factory()->create([
            'tenant_id' => $this->tenant->id,
            'service_id' => $service->id,
            'product_id' => $product->id,
            'quantity_used' => $quantity,
        ]);
    }

    public function test_billing_a_service_deducts_its_linked_products_and_links_the_movement_to_the_bill(): void
    {
        $user = User::factory()->for($this->tenant)->create();
        $client = Client::factory()->create(['tenant_id' => $this->tenant->id]);
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id, 'quantity_on_hand' => 50]);
        $service = Service::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->link($service, $product, 8);

        $bill = app(BillingService::class)->createManualBill($client->id, $user->id, [
            ['description' => $service->name, 'service_id' => $service->id, 'unit_price' => 500],
        ]);

        $this->assertSame('42.00', $product->fresh()->quantity_on_hand);
        $this->assertDatabaseHas('stock_adjustments', [
            'product_id' => $product->id,
            'bill_id' => $bill->id,
            'type' => StockMovementType::Usage->value,
            'quantity_delta' => -8,
        ]);
    }

    public function test_combo_components_deduct_their_own_linked_products(): void
    {
        $user = User::factory()->for($this->tenant)->create();
        $combo = $this->comboWith([500, 300]);
        $components = $this->eligibleComponentStaff($combo);
        $productA = Product::factory()->create(['tenant_id' => $this->tenant->id, 'quantity_on_hand' => 20]);
        $productB = Product::factory()->create(['tenant_id' => $this->tenant->id, 'quantity_on_hand' => 20]);
        $this->link($combo->comboItems[0]->component, $productA, 3);
        $this->link($combo->comboItems[1]->component, $productB, 2);

        app(QuickBillService::class)->createAndSettle([['service_id' => $combo->id, 'components' => $components]], [], 'cash', $user->id);

        $this->assertSame('17.00', $productA->fresh()->quantity_on_hand);
        $this->assertSame('18.00', $productB->fresh()->quantity_on_hand);
    }

    public function test_cancelling_the_bill_restores_the_deducted_stock(): void
    {
        $user = User::factory()->for($this->tenant)->create();
        $client = Client::factory()->create(['tenant_id' => $this->tenant->id]);
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id, 'quantity_on_hand' => 50]);
        $service = Service::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->link($service, $product, 8);
        $billing = app(BillingService::class);
        $bill = $billing->createManualBill($client->id, $user->id, [
            ['description' => $service->name, 'service_id' => $service->id, 'unit_price' => 500],
        ]);

        $billing->cancel($bill, $user->id);

        $this->assertSame('50.00', $product->fresh()->quantity_on_hand);
        $this->assertDatabaseHas('stock_adjustments', [
            'product_id' => $product->id,
            'bill_id' => $bill->id,
            'type' => StockMovementType::UsageReversal->value,
            'quantity_delta' => 8,
        ]);
    }

    public function test_a_count_after_billing_overrides_the_estimate_without_creating_a_purchase(): void
    {
        $user = User::factory()->for($this->tenant)->create();
        $client = Client::factory()->create(['tenant_id' => $this->tenant->id]);
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id, 'quantity_on_hand' => 50]);
        $service = Service::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->link($service, $product, 10);
        app(BillingService::class)->createManualBill($client->id, $user->id, [
            ['description' => $service->name, 'service_id' => $service->id, 'unit_price' => 500],
        ]);

        app(InventoryService::class)->recordCount($product, 46, $user->id);

        $this->assertSame('46.00', $product->fresh()->quantity_on_hand);
        $this->assertDatabaseCount('stock_purchases', 0);
        $this->assertDatabaseHas('stock_adjustments', [
            'product_id' => $product->id,
            'type' => StockMovementType::CountCorrection->value,
            'quantity_delta' => 6,
        ]);
    }
}
