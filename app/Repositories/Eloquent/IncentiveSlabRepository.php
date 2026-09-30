<?php

namespace App\Repositories\Eloquent;

use App\Models\IncentiveSlab;
use App\Repositories\Contracts\IncentiveSlabRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class IncentiveSlabRepository implements IncentiveSlabRepositoryInterface
{
    public function __construct(private IncentiveSlab $model) {}

    public function findById(int $id): ?IncentiveSlab
    {
        return $this->model->find($id);
    }

    /** @return Collection<int, IncentiveSlab> */
    public function getAllAscending(): Collection
    {
        return $this->model->orderBy('min_achievement_percent')->get();
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): IncentiveSlab
    {
        return $this->model->create($data);
    }

    /** @param array<string, mixed> $data */
    public function update(IncentiveSlab $slab, array $data): IncentiveSlab
    {
        $slab->update($data);

        return $slab;
    }

    public function delete(IncentiveSlab $slab): bool
    {
        return (bool) $slab->delete();
    }
}
