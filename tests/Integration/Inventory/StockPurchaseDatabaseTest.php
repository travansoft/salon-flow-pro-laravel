<?php

namespace Tests\Integration\Inventory;

use App\Models\Product;
use App\Models\StockPurchase;
use App\Models\Tenant;
use App\Models\User;
use App\Services\StockPurchaseService;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsTenant;
use Tests\Concerns\CreatesIncentiveBills;
use Tests\TestCase;

class StockPurchaseDatabaseTest extends TestCase
{
    use ActsAsTenant, CreatesIncentiveBills, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenant();
        $this->useTenantAndBranchContext();
    }

    public function test_purchase_persists_with_a_ledger_movement_and_the_new_quantity(): void
    {
        $user = User::factory()->for($this->tenant)->create();
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id, 'quantity_on_hand' => 3]);

        $purchase = app(StockPurchaseService::class)->recordPurchase($product, [
            'quantity' => 7,
            'unit_cost' => 90,
            'purchased_at' => '2026-10-10',
        ], $user->id);

        $this->assertDatabaseHas('stock_purchases', ['id' => $purchase->id, 'product_id' => $product->id, 'tenant_id' => $this->tenant->id]);
        $this->assertDatabaseHas('stock_adjustments', ['purchase_id' => $purchase->id, 'type' => 'purchase', 'quantity_delta' => 7]);
        $this->assertSame('10.00', $product->fresh()->quantity_on_hand);
    }

    public function test_purchases_of_other_tenants_are_not_visible(): void
    {
        $otherTenant = Tenant::factory()->create();
        StockPurchase::factory()->create(['tenant_id' => $otherTenant->id]);
        $own = StockPurchase::factory()->create(['tenant_id' => $this->tenant->id]);

        app(TenantContext::class)->set($this->tenant);

        $this->assertSame([$own->id], StockPurchase::query()->pluck('id')->all());
    }
}
