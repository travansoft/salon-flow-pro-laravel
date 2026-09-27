<?php

namespace Tests\Integration\Services;

use App\Models\Branch;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\Tenant;
use App\Services\BranchContext;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceCatalogDatabaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_persists_with_price_history_relationship(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);

        $service = Service::factory()->create(['tenant_id' => $tenant->id, 'price' => 499]);
        $service->priceHistories()->create([
            'tenant_id' => $tenant->id,
            'price' => 499,
            'effective_from' => now(),
        ]);

        $this->assertSame(1, $service->priceHistories()->count());
    }

    public function test_tenant_scope_excludes_services_from_other_tenants(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        Service::factory()->create(['tenant_id' => $tenantA->id]);
        Service::factory()->create(['tenant_id' => $tenantB->id]);

        app(TenantContext::class)->set($tenantA);
        $branchA = Branch::factory()->create(['tenant_id' => $tenantA->id]);
        app(BranchContext::class)->set($branchA);

        $this->assertSame(1, Service::count());
    }

    public function test_staff_service_pivot_maps_eligible_staff_to_a_service(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);

        $service = Service::factory()->create(['tenant_id' => $tenant->id]);
        $staffProfile = StaffProfile::factory()->create(['tenant_id' => $tenant->id]);

        $service->staff()->sync([$staffProfile->id]);

        $this->assertDatabaseHas('staff_service', [
            'service_id' => $service->id,
            'staff_profile_id' => $staffProfile->id,
        ]);
        $this->assertTrue($staffProfile->fresh()->services->contains($service));
    }

    public function test_deleting_a_staff_profile_cascades_to_the_staff_service_pivot(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);

        $service = Service::factory()->create(['tenant_id' => $tenant->id]);
        $staffProfile = StaffProfile::factory()->create(['tenant_id' => $tenant->id]);
        $service->staff()->sync([$staffProfile->id]);

        $staffProfile->forceDelete();

        $this->assertDatabaseMissing('staff_service', ['staff_profile_id' => $staffProfile->id]);
    }

    public function test_requires_rate_confirmation_persists_as_a_boolean_column(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);

        $service = Service::factory()->create(['tenant_id' => $tenant->id, 'requires_rate_confirmation' => true]);

        $this->assertDatabaseHas('services', ['id' => $service->id, 'requires_rate_confirmation' => true]);
        $this->assertTrue($service->fresh()->requires_rate_confirmation);
    }

    public function test_disabling_a_service_soft_deletes_neither_service_nor_its_price_history(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);

        $service = Service::factory()->create(['tenant_id' => $tenant->id]);
        $service->priceHistories()->create([
            'tenant_id' => $tenant->id,
            'price' => $service->price,
            'effective_from' => now(),
        ]);

        $service->update(['is_active' => false]);

        $this->assertDatabaseHas('services', ['id' => $service->id, 'is_active' => false]);
        $this->assertDatabaseHas('service_price_histories', ['service_id' => $service->id]);
    }
}
