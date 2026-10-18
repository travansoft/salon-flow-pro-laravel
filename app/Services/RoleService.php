<?php

namespace App\Services;

use App\Models\Role;
use App\Models\Tenant;
use App\Repositories\Contracts\RoleRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;

class RoleService
{
    public function __construct(private RoleRepositoryInterface $roleRepository) {}

    /**
     * @param  array{name: string, description?: ?string, permissions?: array<int, string>}  $data
     */
    public function create(?Tenant $tenant, array $data): Role
    {
        return $this->roleRepository->create([
            'tenant_id' => $tenant?->id,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
        ], $data['permissions'] ?? []);
    }

    /**
     * @param  array{name?: string, description?: ?string, permissions?: array<int, string>}  $data
     */
    public function update(Role $role, array $data): Role
    {
        $attributes = [
            'description' => $data['description'] ?? null,
        ];

        if (! $role->isBuiltIn()) {
            $attributes['name'] = $data['name'] ?? $role->name;
        }

        return $this->roleRepository->update($role, $attributes, $data['permissions'] ?? []);
    }

    public function delete(Role $role): void
    {
        if ($role->isBuiltIn()) {
            throw new InvalidArgumentException('Built-in roles cannot be deleted.');
        }

        if ($this->roleRepository->countUsers($role) > 0) {
            throw new InvalidArgumentException('This role is assigned to users. Reassign them before deleting it.');
        }

        $this->roleRepository->delete($role);
    }

    /**
     * Resolves role names to the roles a tenant may use (system roles plus its
     * own), so a name shared by two tenants' custom roles never leaks across.
     *
     * @param  array<int, string>  $names
     * @return Collection<int, Role>
     */
    public function resolveForTenant(array $names, int $tenantId): Collection
    {
        return $this->roleRepository->findAssignableByName($names, $tenantId);
    }
}
