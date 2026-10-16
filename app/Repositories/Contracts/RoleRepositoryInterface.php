<?php

namespace App\Repositories\Contracts;

use App\Models\Role;
use Illuminate\Database\Eloquent\Collection;
use Spatie\Permission\Models\Permission;

interface RoleRepositoryInterface
{
    public function findById(int $id): ?Role;

    /** @return Collection<int, Role> */
    public function getSystemRoles(): Collection;

    /** @return Collection<int, Role> */
    public function getOwnedByTenant(int $tenantId): Collection;

    /** @return Collection<int, Role> */
    public function getAssignableByTenant(int $tenantId): Collection;

    /**
     * @param  array<int, string>  $names
     * @return Collection<int, Role>
     */
    public function findAssignableByName(array $names, ?int $tenantId): Collection;

    /** @return Collection<int, Permission> */
    public function getAllPermissions(): Collection;

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, string>  $permissions
     */
    public function create(array $data, array $permissions): Role;

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, string>  $permissions
     */
    public function update(Role $role, array $data, array $permissions): Role;

    public function delete(Role $role): bool;

    public function countUsers(Role $role): int;
}
