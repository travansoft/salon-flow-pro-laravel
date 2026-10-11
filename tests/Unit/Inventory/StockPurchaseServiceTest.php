<?php

namespace Tests\Unit\Inventory;

use App\Enums\StockMovementType;
use App\Models\Branch;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Services\BranchContext;
use App\Services\StockPurchaseService;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockPurchaseServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_record_purchase_saves_the_purchase_and_increases_stock(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        app(BranchContext::class)->set(Branch::factory()->create(['tenant_id' => $tenant->id]));
        $user = User::factory()->for($tenant)->create();
        $product = Product::factory()->create(['tenant_id' => $tenant->id, 'quantity_on_hand' => 5]);

        $purchase = app(StockPurchaseService::class)->recordPurchase($product, [
            'quantity' => 12,
            'unit_cost' => 80,
            'supplier_name' => 'Acme Beauty',
            'purchased_at' => '2026-10-10',
        ], $user->id);

        $this->assertSame('17.00', $product->fresh()->quantity_on_hand);
        $this->assertSame('12.00', $purchase->quantity);
        $movement = $product->stockAdjustments()->first();
        $this->assertSame(StockMovementType::Purchase, $movement->type);
        $this->assertSame($purchase->id, $movement->purchase_id);
    }
}
