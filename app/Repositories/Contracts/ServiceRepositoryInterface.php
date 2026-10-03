<?php

namespace App\Repositories\Contracts;

use App\Models\Service;
use Illuminate\Database\Eloquent\Collection;

interface ServiceRepositoryInterface
{
    public function findById(int $id): ?Service;

    /** @return Collection<int, Service> */
    public function getActive(): Collection;

    /** @return Collection<int, Service> */
    public function getActiveNonCombo(): Collection;

    /** @return Collection<int, Service> */
    public function search(string $term): Collection;

    /** @param array<string, mixed> $data */
    public function create(array $data): Service;

    /** @param array<string, mixed> $data */
    public function update(Service $service, array $data): Service;

    public function delete(Service $service): bool;
}
