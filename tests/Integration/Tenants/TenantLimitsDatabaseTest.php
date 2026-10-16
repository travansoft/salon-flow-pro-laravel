<?php

namespace Tests\Integration\Tenants;

use App\Exceptions\TenantLimitExceededException;
use App\Models\Branch;
use App\Models\User;
use App\Services\TenantContext;
use App\Services\TenantLimits;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsTenant;
use Tests\TestCase;

class TenantLimitsDatabaseTest extends TestCase
{
    use ActsAsTenant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenant();
        app(TenantContext::class)->set($this->tenant);
    }

    public function test_disabled_users_do_not_count_towards_the_user_limit(): void
    {
        User::factory()->for($this->tenant)->count(3)->create();
        User::factory()->for($this->tenant)->count(2)->create(['disabled_at' => now()]);

        $this->assertSame(3, app(TenantLimits::class)->activeUserCount($this->tenant));
    }

    public function test_user_limit_is_enforced_against_real_rows(): void
    {
        User::factory()->for($this->tenant)->count(5)->create();

        $this->expectException(TenantLimitExceededException::class);

        app(TenantLimits::class)->assertCanAddUser($this->tenant);
    }

    public function test_users_of_other_tenants_do_not_count(): void
    {
        User::factory()->count(5)->create();

        app(TenantLimits::class)->assertCanAddUser($this->tenant);

        $this->assertSame(0, app(TenantLimits::class)->activeUserCount($this->tenant));
    }

    public function test_inactive_and_deleted_branches_do_not_count(): void
    {
        Branch::factory()->create(['tenant_id' => $this->tenant->id, 'is_active' => false]);
        Branch::factory()->create(['tenant_id' => $this->tenant->id])->delete();

        app(TenantLimits::class)->assertCanAddBranch($this->tenant);

        $this->assertSame(0, app(TenantLimits::class)->activeBranchCount($this->tenant));
    }

    public function test_raised_tenant_limit_allows_more_branches(): void
    {
        $this->tenant->update(['max_branches' => 2]);
        Branch::factory()->create(['tenant_id' => $this->tenant->id]);

        app(TenantLimits::class)->assertCanAddBranch($this->tenant->fresh());

        $this->addToAssertionCount(1);
    }
}
