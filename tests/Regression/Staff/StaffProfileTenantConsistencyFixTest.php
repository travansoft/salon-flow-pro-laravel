<?php

namespace Tests\Regression\Staff;

use App\Models\Branch;
use App\Models\StaffProfile;
use App\Models\Tenant;
use App\Models\User;
use App\Services\BranchContext;
use App\Services\StaffService;
use App\Services\TenantContext;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffProfileTenantConsistencyFixTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    /**
     * Bug risk: StaffService::create() built the User and the StaffProfile
     * from two independently-resolved tenants if either write path fell back
     * to a default factory/tenant instead of the active TenantContext. This
     * would let a staff profile's tenant_id diverge from its own user's
     * tenant_id, silently breaking tenant isolation for that record.
     */
    public function test_created_staff_profile_and_its_user_always_share_the_same_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);

        $staffProfile = app(StaffService::class)->create([
            'name' => 'Anjali Menon',
            'create_login' => true,
            'username' => 'anjali',
            'password' => 'password123',
            'roles' => ['Stylist'],
        ]);

        $this->assertSame($tenant->id, $staffProfile->tenant_id);
        $this->assertSame($tenant->id, $staffProfile->user->tenant_id);
    }

    public function test_staff_profile_factory_keeps_user_and_profile_on_the_same_tenant(): void
    {
        $staffProfile = StaffProfile::factory()->create();

        app(TenantContext::class)->set(Tenant::find($staffProfile->tenant_id));
        $branch = Branch::factory()->create(['tenant_id' => $staffProfile->tenant_id]);
        app(BranchContext::class)->set($branch);

        $this->assertSame(
            $staffProfile->tenant_id,
            User::find($staffProfile->user_id)->tenant_id
        );
    }
}
