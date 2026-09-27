<?php

namespace Tests\Integration\Commission;

use App\Models\Branch;
use App\Models\CommissionRate;
use App\Models\ServiceCategory;
use App\Models\StaffProfile;
use App\Models\Tenant;
use App\Services\BranchContext;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommissionRateDatabaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_commission_rate_persists_with_staff_and_category_relationships(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);
        $staff = StaffProfile::factory()->create(['tenant_id' => $tenant->id]);
        $category = ServiceCategory::factory()->create(['tenant_id' => $tenant->id]);

        $rate = CommissionRate::factory()->create([
            'tenant_id' => $tenant->id,
            'staff_profile_id' => $staff->id,
            'service_category_id' => $category->id,
            'rate_percent' => 15,
            'effective_from' => '2026-01-01',
        ]);

        $this->assertDatabaseHas('commission_rates', [
            'id' => $rate->id,
            'staff_profile_id' => $staff->id,
            'service_category_id' => $category->id,
            'rate_percent' => 15,
        ]);
        $this->assertTrue($rate->staffProfile->is($staff));
        $this->assertTrue($rate->serviceCategory->is($category));
    }

    public function test_tenant_scope_excludes_commission_rates_from_other_tenants(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        CommissionRate::factory()->create(['tenant_id' => $tenantA->id]);
        CommissionRate::factory()->create(['tenant_id' => $tenantB->id]);

        app(TenantContext::class)->set($tenantA);
        $branchA = Branch::factory()->create(['tenant_id' => $tenantA->id]);
        app(BranchContext::class)->set($branchA);

        $this->assertSame(1, CommissionRate::count());
    }

    public function test_deleting_a_rate_soft_deletes_it_and_keeps_the_row(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);
        $rate = CommissionRate::factory()->create(['tenant_id' => $tenant->id]);

        $rate->delete();

        $this->assertDatabaseMissing('commission_rates', ['id' => $rate->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('commission_rates', ['id' => $rate->id]);
        $this->assertNotNull(CommissionRate::withTrashed()->find($rate->id)->deleted_at);
    }

    public function test_commission_rate_can_be_created_with_null_staff_and_category_as_a_default(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);

        $rate = CommissionRate::factory()->create([
            'tenant_id' => $tenant->id,
            'staff_profile_id' => null,
            'service_category_id' => null,
        ]);

        $this->assertDatabaseHas('commission_rates', [
            'id' => $rate->id,
            'staff_profile_id' => null,
            'service_category_id' => null,
        ]);
    }
}
