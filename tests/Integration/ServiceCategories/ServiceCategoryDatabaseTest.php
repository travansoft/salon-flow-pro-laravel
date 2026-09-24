<?php

namespace Tests\Integration\ServiceCategories;

use App\Models\Branch;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\Tenant;
use App\Services\BranchContext;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceCategoryDatabaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_categories_are_scoped_per_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        ServiceCategory::factory()->create(['tenant_id' => $tenantA->id, 'name' => 'Hair']);
        ServiceCategory::factory()->create(['tenant_id' => $tenantB->id, 'name' => 'Hair']);

        app(TenantContext::class)->set($tenantA);
        $branchA = Branch::factory()->create(['tenant_id' => $tenantA->id]);
        app(BranchContext::class)->set($branchA);
        $this->assertSame(1, ServiceCategory::count());
    }

    public function test_a_service_resolves_its_category_relation(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);
        $category = ServiceCategory::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Bridal']);
        $service = Service::factory()->create(['tenant_id' => $tenant->id, 'category_id' => $category->id]);

        $this->assertSame('Bridal', $service->fresh()->category->name);
    }

    public function test_deleting_a_category_leaves_its_services_uncategorised_instead_of_deleting_them(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);
        $category = ServiceCategory::factory()->create(['tenant_id' => $tenant->id]);
        $service = Service::factory()->create(['tenant_id' => $tenant->id, 'category_id' => $category->id]);

        $category->delete();

        $this->assertNotNull(Service::find($service->id));
        $this->assertNull($service->fresh()->category);
    }
}
