<?php

namespace App\Repositories\Eloquent;

use App\Models\Service;
use App\Repositories\Contracts\ServiceRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class ServiceRepository implements ServiceRepositoryInterface
{
    public function __construct(private Service $model) {}

    public function findById(int $id): ?Service
    {
        return $this->model->find($id);
    }

    /** @return Collection<int, Service> */
    public function getActive(): Collection
    {
        return $this->model->active()->with('category')->get();
    }

    /** @return Collection<int, Service> */
    public function getActiveNonCombo(): Collection
    {
        return $this->model->active()->where('is_combo', false)->orderBy('name')->get();
    }

    /** @return Collection<int, Service> */
    public function search(string $term): Collection
    {
        return $this->model->active()->with('comboItems.component')->search($term)->orderBy('name')->limit(10)->get();
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): Service
    {
        return $this->model->create($data);
    }

    /** @param array<string, mixed> $data */
    public function update(Service $service, array $data): Service
    {
        $service->update($data);

        return $service;
    }

    public function delete(Service $service): bool
    {
        return (bool) $service->delete();
    }
}
