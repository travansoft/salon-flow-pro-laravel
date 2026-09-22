<?php

namespace App\Repositories\Contracts;

use App\Models\PlatformAdmin;
use Illuminate\Database\Eloquent\Collection;

interface PlatformAdminRepositoryInterface
{
    public function findById(int $id): ?PlatformAdmin;

    /** @return Collection<int, PlatformAdmin> */
    public function getAll(): Collection;

    /** @param array<string, mixed> $data */
    public function create(array $data): PlatformAdmin;

    /** @param array<string, mixed> $data */
    public function update(PlatformAdmin $platformAdmin, array $data): PlatformAdmin;

    public function delete(PlatformAdmin $platformAdmin): bool;
}
