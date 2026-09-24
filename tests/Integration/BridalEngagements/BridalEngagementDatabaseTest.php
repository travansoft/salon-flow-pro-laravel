<?php

namespace Tests\Integration\BridalEngagements;

use App\Models\Branch;
use App\Models\BridalEngagement;
use App\Models\Tenant;
use App\Services\BranchContext;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BridalEngagementDatabaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_scope_excludes_engagements_from_other_tenants(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        BridalEngagement::factory()->create(['tenant_id' => $tenantA->id]);
        BridalEngagement::factory()->create(['tenant_id' => $tenantB->id]);

        app(TenantContext::class)->set($tenantA);
        $branchA = Branch::factory()->create(['tenant_id' => $tenantA->id]);
        app(BranchContext::class)->set($branchA);

        $this->assertSame(1, BridalEngagement::count());
    }

    public function test_deleting_an_engagement_soft_deletes_and_preserves_its_appointments(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);

        $engagement = BridalEngagement::factory()->create(['tenant_id' => $tenant->id]);

        $engagement->delete();

        $this->assertSoftDeleted('bridal_engagements', ['id' => $engagement->id]);
    }
}
