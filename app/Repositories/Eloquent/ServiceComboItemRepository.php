<?php

namespace App\Repositories\Eloquent;

use App\Models\Service;
use App\Models\ServiceComboItem;
use App\Repositories\Contracts\ServiceComboItemRepositoryInterface;

class ServiceComboItemRepository implements ServiceComboItemRepositoryInterface
{
    public function __construct(private ServiceComboItem $model) {}

    /** @param array<int, array{service_id: int|string, price: float|int|string}> $items */
    public function syncForCombo(Service $combo, array $items): void
    {
        $this->model->where('combo_service_id', $combo->id)->delete();

        foreach (array_values($items) as $position => $item) {
            $this->model->create([
                'tenant_id' => $combo->tenant_id,
                'branch_id' => $combo->branch_id,
                'combo_service_id' => $combo->id,
                'component_service_id' => $item['service_id'],
                'price' => $item['price'],
                'sort_order' => $position,
            ]);
        }

        $combo->unsetRelation('comboItems');
    }
}
