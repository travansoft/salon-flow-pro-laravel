<?php

namespace Tests\Integration\Inventory;

use App\Models\Branch;
use App\Models\Product;
use App\Models\StockAdjustment;
use App\Models\Tenant;
use App\Models\User;
use App\Services\BranchContext;
use App\Services\InventoryService;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockAdjustmentAuditTrailTest extends TestCase
{
    use RefreshDatabase;

    public function test_adjusting_stock_creates_an_audit_row_and_updates_quantity_on_hand(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);
        $user = User::factory()->for($tenant)->create();

        $product = Product::factory()->create(['tenant_id' => $tenant->id, 'quantity_on_hand' => 20]);

        $service = app(InventoryService::class);
        $service->adjustStock($product, -8, 'Stock count', $user->id);

        $this->assertDatabaseHas('stock_adjustments', [
            'tenant_id' => $tenant->id,
            'product_id' => $product->id,
            'adjusted_by' => $user->id,
            'quantity_delta' => -8,
            'reason' => 'Stock count',
        ]);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'quantity_on_hand' => 12,
        ]);
    }

    public function test_multiple_adjustments_accumulate_correctly(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);
        $user = User::factory()->for($tenant)->create();

        $product = Product::factory()->create(['tenant_id' => $tenant->id, 'quantity_on_hand' => 10]);

        $service = app(InventoryService::class);
        $service->adjustStock($product, 5, 'Restock', $user->id);
        $service->adjustStock($product, -3, 'Damaged', $user->id);

        $this->assertSame(2, $product->stockAdjustments()->count());
        $this->assertSame('12.00', $product->fresh()->quantity_on_hand);
    }

    public function test_adjustments_are_tenant_scoped(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        app(TenantContext::class)->set($tenantA);
        $branchA = Branch::factory()->create(['tenant_id' => $tenantA->id]);
        app(BranchContext::class)->set($branchA);
        $userA = User::factory()->for($tenantA)->create();
        $productA = Product::factory()->create(['tenant_id' => $tenantA->id]);

        $service = app(InventoryService::class);
        $service->adjustStock($productA, 5, 'Restock', $userA->id);

        app(TenantContext::class)->set($tenantB);
        $branchB = Branch::factory()->create(['tenant_id' => $tenantB->id]);
        app(BranchContext::class)->set($branchB);

        $this->assertSame(0, StockAdjustment::count());
    }
}
