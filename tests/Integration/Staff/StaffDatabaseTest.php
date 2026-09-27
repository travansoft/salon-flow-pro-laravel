<?php

namespace Tests\Integration\Staff;

use App\Models\Branch;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\Tenant;
use App\Services\BranchContext;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffDatabaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_profile_persists_with_correct_relationships(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);

        $staffProfile = StaffProfile::factory()->create(['tenant_id' => $tenant->id]);
        $service = Service::factory()->create(['tenant_id' => $tenant->id]);
        $staffProfile->services()->attach($service);

        $this->assertDatabaseHas('staff_profiles', ['id' => $staffProfile->id]);
        $this->assertTrue($staffProfile->tenant->is($tenant));
        $this->assertTrue($staffProfile->services->contains($service));
    }

    public function test_tenant_scope_excludes_staff_from_other_tenants(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        StaffProfile::factory()->create(['tenant_id' => $tenantA->id]);
        StaffProfile::factory()->create(['tenant_id' => $tenantB->id]);

        app(TenantContext::class)->set($tenantA);
        $branchA = Branch::factory()->create(['tenant_id' => $tenantA->id]);
        app(BranchContext::class)->set($branchA);

        $this->assertSame(1, StaffProfile::count());
    }

    public function test_deleting_staff_profile_soft_deletes_and_preserves_history(): void
    {
        $tenant = Tenant::factory()->create();
        $staffProfile = StaffProfile::factory()->create(['tenant_id' => $tenant->id]);

        $staffProfile->delete();

        $this->assertSoftDeleted('staff_profiles', ['id' => $staffProfile->id]);
    }

    public function test_disabling_login_persists_disabled_at_without_touching_the_staff_profile(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);
        $staffProfile = StaffProfile::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true]);

        $staffProfile->user->update(['disabled_at' => now()]);

        $this->assertDatabaseHas('users', ['id' => $staffProfile->user_id]);
        $this->assertNotNull($staffProfile->user->fresh()->disabled_at);
        $this->assertTrue($staffProfile->fresh()->is_active);
    }
}
