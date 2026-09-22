<?php

namespace App\Repositories\Eloquent;

use App\Models\PlatformAdmin;
use App\Repositories\Contracts\PlatformAdminRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class PlatformAdminRepository implements PlatformAdminRepositoryInterface
{
    public function __construct(private PlatformAdmin $model) {}

    public function findById(int $id): ?PlatformAdmin
    {
        return $this->model->find($id);
    }

    /** @return Collection<int, PlatformAdmin> */
    public function getAll(): Collection
    {
        return $this->model->orderBy('name')->get();
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): PlatformAdmin
    {
        return $this->model->create($data);
    }

    /** @param array<string, mixed> $data */
    public function update(PlatformAdmin $platformAdmin, array $data): PlatformAdmin
    {
        $platformAdmin->update($data);

        return $platformAdmin;
    }

    public function delete(PlatformAdmin $platformAdmin): bool
    {
        return (bool) $platformAdmin->delete();
    }
}
