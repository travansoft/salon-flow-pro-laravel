<?php

namespace App\Repositories\Contracts;

use App\Models\IncentiveSlab;
use Illuminate\Database\Eloquent\Collection;

interface IncentiveSlabRepositoryInterface
{
    public function findById(int $id): ?IncentiveSlab;

    /** @return Collection<int, IncentiveSlab> */
    public function getAllAscending(): Collection;

    /** @param array<string, mixed> $data */
    public function create(array $data): IncentiveSlab;

    /** @param array<string, mixed> $data */
    public function update(IncentiveSlab $slab, array $data): IncentiveSlab;

    public function delete(IncentiveSlab $slab): bool;
}
