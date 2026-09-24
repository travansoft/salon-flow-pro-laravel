<?php

namespace App\Repositories\Eloquent;

use App\Models\Branch;
use App\Repositories\Contracts\BranchRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class BranchRepository implements BranchRepositoryInterface
{
    public function __construct(private Branch $model) {}

    public function findById(int $id): ?Branch
    {
        return $this->model->find($id);
    }

    /** @return Collection<int, Branch> */
    public function getActive(): Collection
    {
        return $this->model->active()->orderBy('name')->get();
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): Branch
    {
        return $this->model->create($data);
    }

    /** @param array<string, mixed> $data */
    public function update(Branch $branch, array $data): Branch
    {
        $branch->update($data);

        return $branch;
    }

    public function delete(Branch $branch): bool
    {
        return (bool) $branch->delete();
    }
}
