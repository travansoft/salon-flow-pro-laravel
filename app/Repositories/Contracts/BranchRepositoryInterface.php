<?php

namespace App\Repositories\Contracts;

use App\Models\Branch;
use Illuminate\Database\Eloquent\Collection;

interface BranchRepositoryInterface
{
    public function findById(int $id): ?Branch;

    /** @return Collection<int, Branch> */
    public function getActive(): Collection;

    /** @param array<string, mixed> $data */
    public function create(array $data): Branch;

    /** @param array<string, mixed> $data */
    public function update(Branch $branch, array $data): Branch;

    public function delete(Branch $branch): bool;
}
