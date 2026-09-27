<?php

namespace Tests\Regression\Inventory;

use App\Models\Branch;
use App\Models\Product;
use App\Models\Tenant;
use App\Services\BranchContext;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SoftDeletedProductSkuCanBeReusedFixTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Mirrors the equivalent fix for services.code: the (tenant_id, sku)
     * uniqueness is enforced with a partial unique index that only applies
     * to deleted_at IS NULL rows, so once a product is soft-deleted its SKU
     * becomes available again for a replacement product.
     */
    public function test_a_new_product_can_reuse_the_sku_of_a_soft_deleted_product(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);

        $original = Product::factory()->create(['tenant_id' => $tenant->id, 'sku' => 'SKU-500']);
        $original->delete();

        $replacement = Product::factory()->create(['tenant_id' => $tenant->id, 'sku' => 'SKU-500']);

        $this->assertSame('SKU-500', $replacement->sku);
        $this->assertTrue(Product::withTrashed()->find($original->id)->trashed());
    }
}
