<?php

namespace App\Services;

use App\Models\Service;
use App\Repositories\Contracts\ServiceComboItemRepositoryInterface;
use App\Repositories\Contracts\ServiceRepositoryInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ServiceCatalogService
{
    public function __construct(
        private ServiceRepositoryInterface $serviceRepository,
        private TenantContext $tenantContext,
        private BranchContext $branchContext,
        private ServiceComboItemRepositoryInterface $comboItemRepository,
    ) {}

    /** @param array<string, mixed> $data */
    public function create(array $data, int $changedBy): Service
    {
        $tenant = $this->tenantContext->get();
        $branch = $this->branchContext->get();

        $isCombo = (bool) ($data['is_combo'] ?? false);
        $comboItems = $isCombo ? $this->validatedComboItems($data) : [];
        $price = $isCombo ? $this->comboPrice($data, $comboItems) : $data['price'];

        return DB::transaction(function () use ($data, $tenant, $branch, $changedBy, $isCombo, $comboItems, $price): Service {
            $service = $this->serviceRepository->create([
                'tenant_id' => $tenant->id,
                'branch_id' => $branch->id,
                'name' => $data['name'],
                'code' => $data['code'] ?? null,
                'category_id' => $data['category_id'] ?? null,
                'price' => $price,
                'is_combo' => $isCombo,
                'requires_rate_confirmation' => $data['requires_rate_confirmation'] ?? false,
                'duration_minutes' => $data['duration_minutes'],
                'is_active' => $data['is_active'] ?? true,
                'tax_rate' => $data['tax_rate'] ?? null,
                'hsn_sac_code' => $data['hsn_sac_code'] ?? null,
            ]);

            $this->recordPriceHistory($service, $price, $changedBy);

            if ($isCombo) {
                $this->comboItemRepository->syncForCombo($service, $comboItems);

                return $service;
            }

            if (! empty($data['staff_ids'])) {
                $service->staff()->sync($data['staff_ids']);
            }

            return $service;
        });
    }

    /** @param array<string, mixed> $data */
    public function update(Service $service, array $data, int $changedBy): Service
    {
        $isCombo = (bool) ($data['is_combo'] ?? $service->is_combo);
        $comboItems = $isCombo && array_key_exists('combo_items', $data) ? $this->validatedComboItems($data) : null;

        if ($isCombo && $comboItems === null && ! $service->is_combo) {
            throw new InvalidArgumentException('A combo needs at least two services.');
        }

        if ($comboItems !== null) {
            $data['price'] = $this->comboPrice($data, $comboItems);
        }

        return DB::transaction(function () use ($service, $data, $changedBy, $isCombo, $comboItems): Service {
            $priceChanged = isset($data['price']) && bccomp((string) $data['price'], (string) $service->price, 2) !== 0;

            $this->serviceRepository->update($service, [
                'name' => $data['name'] ?? $service->name,
                'code' => array_key_exists('code', $data) ? $data['code'] : $service->code,
                'category_id' => array_key_exists('category_id', $data) ? $data['category_id'] : $service->category_id,
                'price' => $data['price'] ?? $service->price,
                'is_combo' => $isCombo,
                'requires_rate_confirmation' => $data['requires_rate_confirmation'] ?? $service->requires_rate_confirmation,
                'duration_minutes' => $data['duration_minutes'] ?? $service->duration_minutes,
                'is_active' => $data['is_active'] ?? $service->is_active,
                'tax_rate' => array_key_exists('tax_rate', $data) ? $data['tax_rate'] : $service->tax_rate,
                'hsn_sac_code' => array_key_exists('hsn_sac_code', $data) ? $data['hsn_sac_code'] : $service->hsn_sac_code,
            ]);

            if ($priceChanged) {
                $this->recordPriceHistory($service, $data['price'], $changedBy);
            }

            if ($comboItems !== null) {
                $this->comboItemRepository->syncForCombo($service, $comboItems);
            }

            if (! $isCombo && array_key_exists('staff_ids', $data)) {
                $service->staff()->sync($data['staff_ids']);
            }

            return $service->refresh();
        });
    }

    public function deactivate(Service $service): Service
    {
        return $this->serviceRepository->update($service, ['is_active' => false]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, array{service_id: int|string, price: float|int|string}>
     */
    private function validatedComboItems(array $data): array
    {
        $items = array_values($data['combo_items'] ?? []);

        if (count($items) < 2) {
            throw new InvalidArgumentException('A combo needs at least two services.');
        }

        $serviceIds = array_column($items, 'service_id');

        if (count($serviceIds) !== count(array_unique($serviceIds))) {
            throw new InvalidArgumentException('A service can only be added to a combo once.');
        }

        return $items;
    }

    /**
     * The combo price is the sum of its component prices unless the user set one.
     *
     * @param  array<string, mixed>  $data
     * @param  array<int, array{service_id: int|string, price: float|int|string}>  $comboItems
     */
    private function comboPrice(array $data, array $comboItems): string
    {
        if (isset($data['price']) && $data['price'] !== '') {
            return (string) $data['price'];
        }

        return array_reduce($comboItems, fn (string $sum, array $item): string => bcadd($sum, (string) $item['price'], 2), '0');
    }

    private function recordPriceHistory(Service $service, float|string $price, int $changedBy): void
    {
        $service->priceHistories()->create([
            'tenant_id' => $service->tenant_id,
            'branch_id' => $service->branch_id,
            'price' => $price,
            'effective_from' => now(),
            'changed_by' => $changedBy,
        ]);
    }
}
