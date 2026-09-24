<?php

namespace Tests\Integration\Inventory;

use App\Models\Branch;
use App\Models\InventoryCategory;
use App\Models\Product;
use App\Models\Service;
use App\Models\Tenant;
use App\Services\BranchContext;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductDatabaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_persists_with_category_relationship(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);

        $category = InventoryCategory::factory()->create(['tenant_id' => $tenant->id]);
        $product = Product::factory()->create(['tenant_id' => $tenant->id, 'category_id' => $category->id]);

        $this->assertDatabaseHas('products', ['id' => $product->id, 'category_id' => $category->id]);
        $this->assertTrue($product->category->is($category));
    }

    public function test_deleting_a_product_soft_deletes_it_without_removing_the_row(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);

        $product = Product::factory()->create(['tenant_id' => $tenant->id]);
        $product->delete();

        $this->assertSoftDeleted('products', ['id' => $product->id]);
        $this->assertDatabaseMissing('products', ['id' => $product->id, 'deleted_at' => null]);
    }

    public function test_tenant_scope_excludes_products_from_other_tenants(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        Product::factory()->create(['tenant_id' => $tenantA->id]);
        Product::factory()->create(['tenant_id' => $tenantB->id]);

        app(TenantContext::class)->set($tenantA);
        $branchA = Branch::factory()->create(['tenant_id' => $tenantA->id]);
        app(BranchContext::class)->set($branchA);

        $this->assertSame(1, Product::count());
    }

    public function test_service_can_be_linked_to_products_it_consumes(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);

        $service = Service::factory()->create(['tenant_id' => $tenant->id]);
        $product = Product::factory()->create(['tenant_id' => $tenant->id]);

        $service->products()->attach($product->id, ['quantity_used' => 10, 'tenant_id' => $tenant->id]);

        $this->assertDatabaseHas('service_product_usages', [
            'service_id' => $service->id,
            'product_id' => $product->id,
            'quantity_used' => 10,
        ]);
        $this->assertTrue($product->services->contains($service));
    }
}
