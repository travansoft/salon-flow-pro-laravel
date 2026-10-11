<?php

namespace App\Repositories\Contracts;

use App\Models\ServiceProductUsage;
use Illuminate\Database\Eloquent\Collection;

interface ServiceProductUsageRepositoryInterface
{
    /** @return Collection<int, ServiceProductUsage> */
    public function getForService(int $serviceId): Collection;

    /**
     * @param  array<int, int>  $serviceIds
     * @return Collection<int, ServiceProductUsage>
     */
    public function getForServices(array $serviceIds): Collection;

    /** @param array<string, mixed> $data */
    public function create(array $data): ServiceProductUsage;

    public function update(ServiceProductUsage $usage, string $quantityUsed): ServiceProductUsage;

    public function delete(ServiceProductUsage $usage): bool;
}
