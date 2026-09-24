<?php

namespace Tests\Unit\Inventory;

use App\Models\Branch;
use App\Models\InventoryCategory;
use App\Models\Tenant;
use App\Repositories\Eloquent\InventoryCategoryRepository;
use App\Services\BranchContext;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryCategoryRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_find_by_id_returns_the_category(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);

        $category = InventoryCategory::factory()->create(['tenant_id' => $tenant->id]);

        $repository = new InventoryCategoryRepository(new InventoryCategory);

        $this->assertTrue($repository->findById($category->id)->is($category));
    }

    public function test_get_all_returns_every_category_ordered_by_name(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);

        InventoryCategory::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Zeta']);
        InventoryCategory::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Alpha']);

        $repository = new InventoryCategoryRepository(new InventoryCategory);
        $categories = $repository->getAll();

        $this->assertSame('Alpha', $categories->first()->name);
    }

    public function test_get_active_excludes_inactive_categories(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);

        InventoryCategory::factory()->create(['tenant_id' => $tenant->id]);
        InventoryCategory::factory()->inactive()->create(['tenant_id' => $tenant->id]);

        $repository = new InventoryCategoryRepository(new InventoryCategory);

        $this->assertCount(1, $repository->getActive());
    }

    public function test_create_persists_a_category(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);

        $repository = new InventoryCategoryRepository(new InventoryCategory);
        $category = $repository->create(['tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'name' => 'Tools']);

        $this->assertDatabaseHas('inventory_categories', ['id' => $category->id, 'name' => 'Tools']);
    }

    public function test_update_modifies_the_category(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);

        $category = InventoryCategory::factory()->create(['tenant_id' => $tenant->id]);

        $repository = new InventoryCategoryRepository(new InventoryCategory);
        $repository->update($category, ['name' => 'Renamed']);

        $this->assertSame('Renamed', $category->fresh()->name);
    }

    public function test_delete_soft_deletes_the_category(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);

        $category = InventoryCategory::factory()->create(['tenant_id' => $tenant->id]);

        $repository = new InventoryCategoryRepository(new InventoryCategory);
        $repository->delete($category);

        $this->assertSoftDeleted('inventory_categories', ['id' => $category->id]);
    }
}
