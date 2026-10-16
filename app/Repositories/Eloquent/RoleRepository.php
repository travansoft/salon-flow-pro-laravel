<?php

namespace App\Repositories\Eloquent;

use App\Models\Role;
use App\Repositories\Contracts\RoleRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Spatie\Permission\Models\Permission;

class RoleRepository implements RoleRepositoryInterface
{
    public function __construct(private Role $model) {}

    public function findById(int $id): ?Role
    {
        return $this->model->find($id);
    }

    /** @return Collection<int, Role> */
    public function getSystemRoles(): Collection
    {
        return $this->model->system()->with('permissions')->orderBy('name')->get();
    }

    /** @return Collection<int, Role> */
    public function getOwnedByTenant(int $tenantId): Collection
    {
        return $this->model->ownedByTenant($tenantId)->with('permissions')->orderBy('name')->get();
    }

    /** @return Collection<int, Role> */
    public function getAssignableByTenant(int $tenantId): Collection
    {
        return $this->model->assignableByTenant($tenantId)->orderBy('name')->get();
    }

    /**
     * @param  array<int, string>  $names
     * @return Collection<int, Role>
     */
    public function findAssignableByName(array $names, ?int $tenantId): Collection
    {
        $query = $this->model->whereIn('name', $names);

        return $tenantId === null
            ? $query->system()->get()
            : $query->assignableByTenant($tenantId)->get();
    }

    /** @return Collection<int, Permission> */
    public function getAllPermissions(): Collection
    {
        return Permission::orderBy('name')->get();
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, string>  $permissions
     */
    public function create(array $data, array $permissions): Role
    {
        $role = $this->model->newQuery()->create($data + ['guard_name' => 'web']);

        $role->syncPermissions($permissions);

        return $role;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, string>  $permissions
     */
    public function update(Role $role, array $data, array $permissions): Role
    {
        $role->update($data);

        $role->syncPermissions($permissions);

        return $role;
    }

    public function delete(Role $role): bool
    {
        return (bool) $role->delete();
    }

    public function countUsers(Role $role): int
    {
        return $role->users()->count();
    }
}
