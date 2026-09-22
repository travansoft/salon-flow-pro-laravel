<?php

namespace App\Repositories\Contracts;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Collection;

interface TenantRepositoryInterface
{
    public function findById(int $id): ?Tenant;

    public function findActiveByCustomDomain(string $domain): ?Tenant;

    public function findActiveBySubdomain(string $subdomain): ?Tenant;

    public function findActiveBySlug(string $slug): ?Tenant;

    /** @return Collection<int, Tenant> */
    public function getAllActive(): Collection;

    /** @return Collection<int, Tenant> */
    public function getAll(): Collection;

    /** @param array<string, mixed> $data */
    public function create(array $data): Tenant;

    /** @param array<string, mixed> $data */
    public function update(Tenant $tenant, array $data): Tenant;
}
