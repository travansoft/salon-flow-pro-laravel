<?php

namespace App\Repositories\Eloquent;

use App\Models\Tenant;
use App\Models\User;
use App\Repositories\Contracts\TenantUserRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class TenantUserRepository implements TenantUserRepositoryInterface
{
    public function __construct(private User $model) {}

    public function findById(int $id): ?User
    {
        return $this->model->find($id);
    }

    /** @return Collection<int, User> */
    public function getByTenant(Tenant $tenant): Collection
    {
        return $this->model->where('tenant_id', $tenant->id)->orderBy('name')->get();
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): User
    {
        return $this->model->create($data);
    }

    /** @param array<string, mixed> $data */
    public function update(User $user, array $data): User
    {
        $user->update($data);

        return $user;
    }

    public function delete(User $user): bool
    {
        return (bool) $user->delete();
    }
}
