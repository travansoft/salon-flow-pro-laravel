<?php

namespace Tests\Unit\Inventory;

use App\Enums\StockMovementType;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Product;
use App\Models\Service;
use App\Models\ServiceProductUsage;
use App\Models\Tenant;
use App\Models\User;
use App\Services\BillingService;
use App\Services\BranchContext;
use App\Services\StockUsageService;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockUsageServiceTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($this->tenant);
        app(BranchContext::class)->set(Branch::factory()->create(['tenant_id' => $this->tenant->id]));
        $this->user = User::factory()->for($this->tenant)->create();
        $this->client = Client::factory()->create(['tenant_id' => $this->tenant->id]);
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

    public function test_consume_for_bill_multiplies_usage_by_line_quantity_and_aggregates_per_product(): void
    {
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id, 'quantity_on_hand' => 100]);
        $serviceA = Service::factory()->create(['tenant_id' => $this->tenant->id]);
        $serviceB = Service::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->link($serviceA, $product, 10);
        $this->link($serviceB, $product, 5);

        app(BillingService::class)->createManualBill($this->client->id, $this->user->id, [
            ['description' => 'A', 'service_id' => $serviceA->id, 'quantity' => 2, 'unit_price' => 100],
            ['description' => 'B', 'service_id' => $serviceB->id, 'quantity' => 1, 'unit_price' => 100],
        ]);

        $this->assertSame('75.00', $product->fresh()->quantity_on_hand);
        $this->assertSame(1, $product->stockAdjustments()->where('type', StockMovementType::Usage->value)->count());
    }

    public function test_consume_for_bill_ignores_services_without_linked_products(): void
    {
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id, 'quantity_on_hand' => 100]);
        $service = Service::factory()->create(['tenant_id' => $this->tenant->id]);

        app(BillingService::class)->createManualBill($this->client->id, $this->user->id, [
            ['description' => 'A', 'service_id' => $service->id, 'unit_price' => 100],
            ['description' => 'Manual item', 'unit_price' => 50],
        ]);

        $this->assertSame('100.00', $product->fresh()->quantity_on_hand);
        $this->assertSame(0, $product->stockAdjustments()->count());
    }

    public function test_restore_for_bill_is_idempotent(): void
    {
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id, 'quantity_on_hand' => 100]);
        $service = Service::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->link($service, $product, 10);
        $bill = app(BillingService::class)->createManualBill($this->client->id, $this->user->id, [
            ['description' => 'A', 'service_id' => $service->id, 'unit_price' => 100],
        ]);

        $usageService = app(StockUsageService::class);
        $usageService->restoreForBill($bill);
        $usageService->restoreForBill($bill);

        $this->assertSame('100.00', $product->fresh()->quantity_on_hand);
        $this->assertSame(1, $product->stockAdjustments()->where('type', StockMovementType::UsageReversal->value)->count());
    }
}
