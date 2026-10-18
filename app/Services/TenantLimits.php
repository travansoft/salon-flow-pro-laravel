<?php

namespace App\Services;

use App\Exceptions\TenantLimitExceededException;
use App\Models\Tenant;
use App\Repositories\Contracts\BranchRepositoryInterface;
use App\Repositories\Contracts\TenantUserRepositoryInterface;

class TenantLimits
{
    public function __construct(
        private TenantUserRepositoryInterface $tenantUserRepository,
        private BranchRepositoryInterface $branchRepository,
    ) {}

    public function activeUserCount(Tenant $tenant): int
    {
        return $this->tenantUserRepository->countEnabledInTenant($tenant);
    }

    public function activeBranchCount(Tenant $tenant): int
    {
        return $this->branchRepository->countActiveInTenant($tenant);
    }

    public function assertCanAddUser(Tenant $tenant): void
    {
        if ($this->activeUserCount($tenant) >= $tenant->userLimit()) {
            throw TenantLimitExceededException::forUsers($tenant->userLimit());
        }
    }

    public function assertCanAddBranch(Tenant $tenant): void
    {
        if ($this->activeBranchCount($tenant) >= $tenant->branchLimit()) {
            throw TenantLimitExceededException::forBranches($tenant->branchLimit());
        }
    }
}
