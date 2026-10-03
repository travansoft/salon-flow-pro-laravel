<?php

namespace Tests\Concerns;

use App\Models\Service;
use App\Models\ServiceComboItem;
use App\Models\StaffProfile;

/** Requires ActsAsTenant. */
trait CreatesCombos
{
    /**
     * @param  array<int, int|string>  $componentPrices
     */
    protected function comboWith(array $componentPrices, int|string|null $comboPrice = null): Service
    {
        $components = array_map(
            fn (int|string $price): Service => Service::factory()->create(['tenant_id' => $this->tenant->id, 'price' => $price]),
            $componentPrices,
        );

        $combo = Service::factory()->create([
            'tenant_id' => $this->tenant->id,
            'is_combo' => true,
            'price' => $comboPrice ?? array_sum($componentPrices),
        ]);

        $items = [];

        foreach ($components as $position => $component) {
            $items[] = ServiceComboItem::factory()->create([
                'tenant_id' => $this->tenant->id,
                'branch_id' => $combo->branch_id,
                'combo_service_id' => $combo->id,
                'component_service_id' => $component->id,
                'price' => $componentPrices[$position],
                'sort_order' => $position,
            ])->setRelation('component', $component);
        }

        return $combo->setRelation('comboItems', collect($items));
    }

    /**
     * @return array<int, array{service_id: int, staff_profile_id: int}>
     */
    protected function eligibleComponentStaff(Service $combo): array
    {
        return $combo->comboItems->map(function (ServiceComboItem $item): array {
            $staff = StaffProfile::factory()->create(['tenant_id' => $this->tenant->id]);
            $item->component->staff()->attach($staff->id);

            return ['service_id' => $item->component_service_id, 'staff_profile_id' => $staff->id];
        })->all();
    }
}
