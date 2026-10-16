<?php

namespace Tests\Unit\Tenants;

use App\Exceptions\TenantLimitExceededException;
use App\Models\Tenant;
use App\Repositories\Contracts\BranchRepositoryInterface;
use App\Repositories\Contracts\TenantUserRepositoryInterface;
use App\Services\TenantLimits;
use Mockery;
use Tests\TestCase;

class TenantLimitsTest extends TestCase
{
    public function test_defaults_are_one_branch_and_five_users(): void
    {
        $tenant = new Tenant;

        $this->assertSame(5, $tenant->userLimit());
        $this->assertSame(1, $tenant->branchLimit());
    }

    public function test_tenant_override_wins_over_config_default(): void
    {
        $tenant = new Tenant(['max_users' => 12, 'max_branches' => 3]);

        $this->assertSame(12, $tenant->userLimit());
        $this->assertSame(3, $tenant->branchLimit());
    }

    public function test_config_default_can_be_changed(): void
    {
        config(['tenancy.limits.max_users' => 9]);

        $this->assertSame(9, (new Tenant)->userLimit());
    }

    public function test_assert_can_add_user_throws_at_the_limit(): void
    {
        $users = Mockery::mock(TenantUserRepositoryInterface::class);
        $users->shouldReceive('countEnabledInTenant')->andReturn(5);
        $limits = new TenantLimits($users, Mockery::mock(BranchRepositoryInterface::class));

        $this->expectException(TenantLimitExceededException::class);

        $limits->assertCanAddUser(new Tenant);
    }

    public function test_assert_can_add_user_passes_below_the_limit(): void
    {
        $users = Mockery::mock(TenantUserRepositoryInterface::class);
        $users->shouldReceive('countEnabledInTenant')->andReturn(4);
        $limits = new TenantLimits($users, Mockery::mock(BranchRepositoryInterface::class));

        $limits->assertCanAddUser(new Tenant);

        $this->addToAssertionCount(1);
    }

    public function test_assert_can_add_branch_throws_at_the_limit(): void
    {
        $branches = Mockery::mock(BranchRepositoryInterface::class);
        $branches->shouldReceive('countActiveInTenant')->andReturn(1);
        $limits = new TenantLimits(Mockery::mock(TenantUserRepositoryInterface::class), $branches);

        $this->expectException(TenantLimitExceededException::class);

        $limits->assertCanAddBranch(new Tenant);
    }
}
