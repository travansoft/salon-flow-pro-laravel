<?php

namespace App\Repositories\Eloquent;

use App\Models\ServiceProductUsage;
use App\Repositories\Contracts\ServiceProductUsageRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class ServiceProductUsageRepository implements ServiceProductUsageRepositoryInterface
{
    public function __construct(private ServiceProductUsage $model) {}

    /** @return Collection<int, ServiceProductUsage> */
    public function getForService(int $serviceId): Collection
    {
        return $this->model
            ->where('service_id', $serviceId)
            ->with('product')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  array<int, int>  $serviceIds
     * @return Collection<int, ServiceProductUsage>
     */
    public function getForServices(array $serviceIds): Collection
    {
        return $this->model
            ->whereIn('service_id', $serviceIds)
            ->get();
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): ServiceProductUsage
    {
        return $this->model->create($data);
    }

    public function update(ServiceProductUsage $usage, string $quantityUsed): ServiceProductUsage
    {
        $usage->update(['quantity_used' => $quantityUsed]);

        return $usage;
    }

    public function delete(ServiceProductUsage $usage): bool
    {
        return (bool) $usage->delete();
    }
}
