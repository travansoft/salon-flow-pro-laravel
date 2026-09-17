<?php

namespace App\Repositories\Eloquent;

use App\Models\Tenant;
use App\Repositories\Contracts\TenantRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class TenantRepository implements TenantRepositoryInterface
{
    public function __construct(private Tenant $model) {}

    public function findActiveByCustomDomain(string $domain): ?Tenant
    {
        return $this->model->active()->where('custom_domain', $domain)->first();
    }

    public function findActiveBySubdomain(string $subdomain): ?Tenant
    {
        return $this->model->active()->where('subdomain', $subdomain)->first();
    }

    public function findActiveBySlug(string $slug): ?Tenant
    {
        return $this->model->active()->where('slug', $slug)->first();
    }

    /** @return Collection<int, Tenant> */
    public function getAllActive(): Collection
    {
        return $this->model->active()->get();
    }

    /** @param array<string, mixed> $data */
    public function update(Tenant $tenant, array $data): Tenant
    {
        $tenant->update($data);

        return $tenant;
    }
}
