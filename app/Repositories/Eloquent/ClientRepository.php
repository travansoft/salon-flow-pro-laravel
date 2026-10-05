<?php

namespace App\Repositories\Eloquent;

use App\Models\Client;
use App\Repositories\Contracts\ClientRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class ClientRepository implements ClientRepositoryInterface
{
    public function __construct(private Client $model) {}

    public function findById(int $id): ?Client
    {
        return $this->model->find($id);
    }

    public function findByPhone(string $phone): ?Client
    {
        return $this->model->where('phone', $phone)->first();
    }

    /** @return Collection<int, Client> */
    public function getAll(): Collection
    {
        return $this->model->orderBy('name')->get();
    }

    /** @return Collection<int, Client> */
    public function search(string $term): Collection
    {
        return $this->model->search($term)->orderBy('name')->get();
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): Client
    {
        return $this->model->create($data);
    }

    /** @param array<string, mixed> $data */
    public function update(Client $client, array $data): Client
    {
        $client->update($data);

        return $client;
    }
}
