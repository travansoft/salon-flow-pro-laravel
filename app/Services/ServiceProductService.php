<?php

namespace App\Services;

use App\Models\Service;
use App\Models\ServiceProductUsage;
use App\Repositories\Contracts\ServiceProductUsageRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class ServiceProductService
{
    public function __construct(
        private ServiceProductUsageRepositoryInterface $usageRepository,
        private TenantContext $tenantContext,
        private BranchContext $branchContext,
    ) {}

    /** @return Collection<int, ServiceProductUsage> */
    public function getForService(Service $service): Collection
    {
        return $this->usageRepository->getForService($service->id);
    }

    public function addProduct(Service $service, int $productId, float $quantityUsed): ServiceProductUsage
    {
        return $this->usageRepository->create([
            'tenant_id' => $this->tenantContext->get()->id,
            'branch_id' => $this->branchContext->get()->id,
            'service_id' => $service->id,
            'product_id' => $productId,
            'quantity_used' => $quantityUsed,
        ]);
    }

    public function updateQuantity(ServiceProductUsage $usage, float $quantityUsed): ServiceProductUsage
    {
        return $this->usageRepository->update($usage, (string) $quantityUsed);
    }

    public function removeProduct(ServiceProductUsage $usage): bool
    {
        return $this->usageRepository->delete($usage);
    }
}
