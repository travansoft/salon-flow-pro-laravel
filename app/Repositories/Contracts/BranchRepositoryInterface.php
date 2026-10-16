<?php

namespace App\Repositories\Contracts;

use App\Models\Branch;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Collection;

interface BranchRepositoryInterface
{
    public function findById(int $id): ?Branch;

    /** @return Collection<int, Branch> */
    public function getActive(): Collection;

    public function countActiveInTenant(Tenant $tenant): int;

    /** @param array<string, mixed> $data */
    public function create(array $data): Branch;

    /** @param array<string, mixed> $data */
    public function update(Branch $branch, array $data): Branch;

    public function delete(Branch $branch): bool;
}
