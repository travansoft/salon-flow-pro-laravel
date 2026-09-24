<?php

namespace Tests\Integration\Designations;

use App\Models\Branch;
use App\Models\Designation;
use App\Models\StaffProfile;
use App\Models\Tenant;
use App\Services\BranchContext;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DesignationDatabaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_designations_are_scoped_per_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        Designation::factory()->create(['tenant_id' => $tenantA->id, 'name' => 'Manager']);
        Designation::factory()->create(['tenant_id' => $tenantB->id, 'name' => 'Manager']);

        app(TenantContext::class)->set($tenantA);
        $branchA = Branch::factory()->create(['tenant_id' => $tenantA->id]);
        app(BranchContext::class)->set($branchA);
        $this->assertSame(1, Designation::count());
    }

    public function test_a_staff_profile_resolves_its_designation_relation(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);
        $designation = Designation::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Manager']);
        $staffProfile = StaffProfile::factory()->create(['tenant_id' => $tenant->id, 'designation_id' => $designation->id]);

        $this->assertSame('Manager', $staffProfile->fresh()->designation->name);
    }

    public function test_deleting_a_designation_leaves_staff_uncategorised_instead_of_deleting_them(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);
        $designation = Designation::factory()->create(['tenant_id' => $tenant->id]);
        $staffProfile = StaffProfile::factory()->create(['tenant_id' => $tenant->id, 'designation_id' => $designation->id]);

        $designation->delete();

        $this->assertNotNull(StaffProfile::find($staffProfile->id));
        $this->assertNull($staffProfile->fresh()->designation);
    }
}
