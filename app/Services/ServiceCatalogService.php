<?php

namespace App\Services;

use App\Models\Service;
use App\Repositories\Contracts\ServiceRepositoryInterface;
use Illuminate\Support\Facades\DB;

class ServiceCatalogService
{
    public function __construct(
        private ServiceRepositoryInterface $serviceRepository,
        private TenantContext $tenantContext,
    ) {}

    /** @param array<string, mixed> $data */
    public function create(array $data, int $changedBy): Service
    {
        $tenant = $this->tenantContext->get();

        return DB::transaction(function () use ($data, $tenant, $changedBy): Service {
            $service = $this->serviceRepository->create([
                'tenant_id' => $tenant->id,
                'name' => $data['name'],
                'code' => $data['code'] ?? null,
                'category_id' => $data['category_id'] ?? null,
                'price' => $data['price'],
                'duration_minutes' => $data['duration_minutes'],
                'is_active' => $data['is_active'] ?? true,
                'tax_rate' => $data['tax_rate'] ?? null,
                'hsn_sac_code' => $data['hsn_sac_code'] ?? null,
            ]);

            $this->recordPriceHistory($service, $data['price'], $changedBy);

            if (! empty($data['staff_ids'])) {
                $service->staff()->sync($data['staff_ids']);
            }

            return $service;
        });
    }

    /** @param array<string, mixed> $data */
    public function update(Service $service, array $data, int $changedBy): Service
    {
        return DB::transaction(function () use ($service, $data, $changedBy): Service {
            $priceChanged = array_key_exists('price', $data) && bccomp((string) $data['price'], (string) $service->price, 2) !== 0;

            $this->serviceRepository->update($service, [
                'name' => $data['name'] ?? $service->name,
                'code' => array_key_exists('code', $data) ? $data['code'] : $service->code,
                'category_id' => array_key_exists('category_id', $data) ? $data['category_id'] : $service->category_id,
                'price' => $data['price'] ?? $service->price,
                'duration_minutes' => $data['duration_minutes'] ?? $service->duration_minutes,
                'is_active' => $data['is_active'] ?? $service->is_active,
                'tax_rate' => array_key_exists('tax_rate', $data) ? $data['tax_rate'] : $service->tax_rate,
                'hsn_sac_code' => array_key_exists('hsn_sac_code', $data) ? $data['hsn_sac_code'] : $service->hsn_sac_code,
            ]);

            if ($priceChanged) {
                $this->recordPriceHistory($service, $data['price'], $changedBy);
            }

            if (array_key_exists('staff_ids', $data)) {
                $service->staff()->sync($data['staff_ids']);
            }

            return $service->refresh();
        });
    }

    public function deactivate(Service $service): Service
    {
        return $this->serviceRepository->update($service, ['is_active' => false]);
    }

    private function recordPriceHistory(Service $service, float|string $price, int $changedBy): void
    {
        $service->priceHistories()->create([
            'tenant_id' => $service->tenant_id,
            'price' => $price,
            'effective_from' => now(),
            'changed_by' => $changedBy,
        ]);
    }
}
