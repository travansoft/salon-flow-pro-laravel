<?php

namespace Tests\Unit\Inventory;

use App\Models\Branch;
use App\Models\InventoryCategory;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Repositories\Contracts\InventoryCategoryRepositoryInterface;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Services\BranchContext;
use App\Services\InventoryService;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class InventoryServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_product_persists_via_repository_with_tenant_id(): void
    {
        $tenant = Tenant::factory()->create();
        $tenantContext = app(TenantContext::class);
        $tenantContext->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);

        $productRepository = Mockery::mock(ProductRepositoryInterface::class);
        $productRepository->shouldReceive('create')
            ->once()
            ->with(Mockery::on(fn (array $data) => $data['tenant_id'] === $tenant->id && $data['name'] === 'Shampoo'))
            ->andReturnUsing(fn (array $data) => Product::create($data));

        $categoryRepository = Mockery::mock(InventoryCategoryRepositoryInterface::class);

        $service = new InventoryService($productRepository, $categoryRepository, $tenantContext, app(BranchContext::class));

        $created = $service->createProduct(['name' => 'Shampoo', 'quantity_on_hand' => 10, 'reorder_level' => 2]);

        $this->assertSame('Shampoo', $created->name);
    }

    public function test_update_product_keeps_existing_values_when_not_provided(): void
    {
        $tenant = Tenant::factory()->create();
        $tenantContext = app(TenantContext::class);
        $tenantContext->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);

        $product = Product::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Original']);

        $productRepository = Mockery::mock(ProductRepositoryInterface::class);
        $productRepository->shouldReceive('update')
            ->once()
            ->andReturnUsing(function ($prod, array $data) {
                $prod->update($data);

                return $prod;
            });

        $categoryRepository = Mockery::mock(InventoryCategoryRepositoryInterface::class);

        $service = new InventoryService($productRepository, $categoryRepository, $tenantContext, app(BranchContext::class));
        $service->updateProduct($product, ['reorder_level' => 5]);

        $this->assertSame('Original', $product->fresh()->name);
        $this->assertSame('5.00', $product->fresh()->reorder_level);
    }

    public function test_delete_product_delegates_to_repository(): void
    {
        $tenant = Tenant::factory()->create();
        $tenantContext = app(TenantContext::class);
        $tenantContext->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);

        $product = Product::factory()->create(['tenant_id' => $tenant->id]);

        $productRepository = Mockery::mock(ProductRepositoryInterface::class);
        $productRepository->shouldReceive('delete')->once()->with($product)->andReturn(true);

        $categoryRepository = Mockery::mock(InventoryCategoryRepositoryInterface::class);

        $service = new InventoryService($productRepository, $categoryRepository, $tenantContext, app(BranchContext::class));

        $this->assertTrue($service->deleteProduct($product));
    }

    public function test_adjust_stock_increases_quantity_and_records_adjustment(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);
        $user = User::factory()->for($tenant)->create();

        $productRepository = app(ProductRepositoryInterface::class);
        $categoryRepository = app(InventoryCategoryRepositoryInterface::class);

        $product = Product::factory()->create(['tenant_id' => $tenant->id, 'quantity_on_hand' => 10]);

        $service = new InventoryService($productRepository, $categoryRepository, app(TenantContext::class), app(BranchContext::class));
        $updated = $service->adjustStock($product, 5, 'Restock', $user->id);

        $this->assertSame('15.00', $updated->quantity_on_hand);
        $this->assertSame(1, $product->stockAdjustments()->count());
    }

    public function test_adjust_stock_supports_negative_deltas(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);
        $user = User::factory()->for($tenant)->create();

        $productRepository = app(ProductRepositoryInterface::class);
        $categoryRepository = app(InventoryCategoryRepositoryInterface::class);

        $product = Product::factory()->create(['tenant_id' => $tenant->id, 'quantity_on_hand' => 10]);

        $service = new InventoryService($productRepository, $categoryRepository, app(TenantContext::class), app(BranchContext::class));
        $updated = $service->adjustStock($product, -4, 'Damaged', $user->id);

        $this->assertSame('6.00', $updated->quantity_on_hand);

        $adjustment = $product->stockAdjustments()->first();
        $this->assertSame('-4.00', $adjustment->quantity_delta);
        $this->assertSame('Damaged', $adjustment->reason);
    }

    public function test_create_category_persists_via_repository_with_tenant_id(): void
    {
        $tenant = Tenant::factory()->create();
        $tenantContext = app(TenantContext::class);
        $tenantContext->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);

        $categoryRepository = Mockery::mock(InventoryCategoryRepositoryInterface::class);
        $categoryRepository->shouldReceive('create')
            ->once()
            ->with(Mockery::on(fn (array $data) => $data['tenant_id'] === $tenant->id && $data['name'] === 'Skincare'))
            ->andReturnUsing(fn (array $data) => InventoryCategory::create($data));

        $productRepository = Mockery::mock(ProductRepositoryInterface::class);

        $service = new InventoryService($productRepository, $categoryRepository, $tenantContext, app(BranchContext::class));
        $created = $service->createCategory(['name' => 'Skincare']);

        $this->assertSame('Skincare', $created->name);
    }

    public function test_delete_category_delegates_to_repository(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);

        $category = InventoryCategory::factory()->create(['tenant_id' => $tenant->id]);

        $categoryRepository = Mockery::mock(InventoryCategoryRepositoryInterface::class);
        $categoryRepository->shouldReceive('delete')->once()->with($category)->andReturn(true);

        $productRepository = Mockery::mock(ProductRepositoryInterface::class);

        $service = new InventoryService($productRepository, $categoryRepository, app(TenantContext::class), app(BranchContext::class));

        $this->assertTrue($service->deleteCategory($category));
    }
}
