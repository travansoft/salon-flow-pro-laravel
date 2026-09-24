<?php

namespace Tests\Unit\Inventory;

use App\Models\Branch;
use App\Models\Product;
use App\Models\Tenant;
use App\Repositories\Eloquent\ProductRepository;
use App\Services\BranchContext;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_find_by_id_returns_the_product(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);

        $product = Product::factory()->create(['tenant_id' => $tenant->id]);

        $repository = new ProductRepository(new Product);

        $this->assertTrue($repository->findById($product->id)->is($product));
    }

    public function test_find_by_id_returns_null_when_missing(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);

        $repository = new ProductRepository(new Product);

        $this->assertNull($repository->findById(999999));
    }

    public function test_get_active_excludes_inactive_products(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);

        Product::factory()->create(['tenant_id' => $tenant->id]);
        Product::factory()->inactive()->create(['tenant_id' => $tenant->id]);

        $repository = new ProductRepository(new Product);

        $this->assertCount(1, $repository->getActive());
    }

    public function test_get_low_stock_returns_only_products_at_or_below_reorder_level(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);

        Product::factory()->lowStock()->create(['tenant_id' => $tenant->id]);
        Product::factory()->create(['tenant_id' => $tenant->id, 'quantity_on_hand' => 100, 'reorder_level' => 10]);

        $repository = new ProductRepository(new Product);

        $this->assertCount(1, $repository->getLowStock());
    }

    public function test_create_persists_a_product(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);

        $repository = new ProductRepository(new Product);

        $product = $repository->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'name' => 'Conditioner',
            'quantity_on_hand' => 5,
            'reorder_level' => 1,
            'unit' => 'ml',
        ]);

        $this->assertDatabaseHas('products', ['id' => $product->id, 'name' => 'Conditioner']);
    }

    public function test_update_modifies_the_product(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);

        $product = Product::factory()->create(['tenant_id' => $tenant->id]);

        $repository = new ProductRepository(new Product);
        $repository->update($product, ['name' => 'Renamed']);

        $this->assertSame('Renamed', $product->fresh()->name);
    }

    public function test_delete_soft_deletes_the_product(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);

        $product = Product::factory()->create(['tenant_id' => $tenant->id]);

        $repository = new ProductRepository(new Product);
        $repository->delete($product);

        $this->assertSoftDeleted('products', ['id' => $product->id]);
    }
}
