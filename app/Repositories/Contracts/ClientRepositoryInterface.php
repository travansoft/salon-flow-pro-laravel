<?php

namespace App\Repositories\Contracts;

use App\Models\Client;
use Illuminate\Database\Eloquent\Collection;

interface ClientRepositoryInterface
{
    public function findById(int $id): ?Client;

    public function findByPhone(string $phone): ?Client;

    /** @return Collection<int, Client> */
    public function getAll(): Collection;

    /** @return Collection<int, Client> */
    public function search(string $term): Collection;

    /** @param array<string, mixed> $data */
    public function create(array $data): Client;

    /** @param array<string, mixed> $data */
    public function update(Client $client, array $data): Client;
}
