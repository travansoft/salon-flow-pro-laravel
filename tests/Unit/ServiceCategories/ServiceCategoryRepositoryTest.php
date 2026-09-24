<?php

namespace Tests\Unit\ServiceCategories;

use App\Models\Branch;
use App\Models\ServiceCategory;
use App\Models\Tenant;
use App\Repositories\Eloquent\ServiceCategoryRepository;
use App\Services\BranchContext;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceCategoryRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_active_excludes_disabled_categories(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);
        ServiceCategory::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Hair']);
        ServiceCategory::factory()->inactive()->create(['tenant_id' => $tenant->id, 'name' => 'Retired']);

        $repository = app(ServiceCategoryRepository::class);

        $names = $repository->getActive()->pluck('name');

        $this->assertTrue($names->contains('Hair'));
        $this->assertFalse($names->contains('Retired'));
    }

    public function test_get_all_returns_categories_ordered_by_name(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);
        ServiceCategory::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Skin']);
        ServiceCategory::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Hair']);

        $repository = app(ServiceCategoryRepository::class);

        $names = $repository->getAll()->pluck('name')->all();

        $this->assertSame(['Hair', 'Skin'], $names);
    }

    public function test_delete_soft_deletes_the_category(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);
        $category = ServiceCategory::factory()->create(['tenant_id' => $tenant->id]);

        $repository = app(ServiceCategoryRepository::class);
        $repository->delete($category);

        $this->assertSoftDeleted('service_categories', ['id' => $category->id]);
    }
}
