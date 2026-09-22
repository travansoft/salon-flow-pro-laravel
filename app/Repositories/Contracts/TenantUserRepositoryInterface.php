<?php

namespace App\Repositories\Contracts;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

interface TenantUserRepositoryInterface
{
    public function findById(int $id): ?User;

    /** @return Collection<int, User> */
    public function getByTenant(Tenant $tenant): Collection;

    /** @param array<string, mixed> $data */
    public function create(array $data): User;

    /** @param array<string, mixed> $data */
    public function update(User $user, array $data): User;

    public function delete(User $user): bool;
}
