<?php

namespace App\Repositories\Contracts;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Collection;

interface TenantRepositoryInterface
{
    public function findActiveByCustomDomain(string $domain): ?Tenant;

    public function findActiveBySubdomain(string $subdomain): ?Tenant;

    public function findActiveBySlug(string $slug): ?Tenant;

    /** @return Collection<int, Tenant> */
    public function getAllActive(): Collection;

    /** @param array<string, mixed> $data */
    public function update(Tenant $tenant, array $data): Tenant;
}
