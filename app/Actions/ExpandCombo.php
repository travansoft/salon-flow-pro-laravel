<?php

namespace App\Actions;

use App\Models\Service;
use App\Services\TenantContext;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ExpandCombo
{
    public function __construct(private TenantContext $tenantContext) {}

    /**
     * Expands one billed combo into a line per component. Component prices are
     * scaled so the lines add up to the combo price (the combo's own price unless
     * overridden), and the single referrer is stamped on every line.
     *
     * @param  array{
     *     service_id: int,
     *     components?: array<int, array{service_id: int, staff_profile_id: int|null}>,
     *     referred_by_staff_profile_id?: int|null,
     *     unit_price?: float|string|null,
     * }  $item
     * @return array<int, array<string, mixed>>
     */
    public function execute(array $item): array
    {
        $combo = Service::query()->active()->where('is_combo', true)->with('comboItems.component')->find($item['service_id']);

        if (! $combo) {
            throw new InvalidArgumentException('One of the selected combos is no longer available.');
        }

        $staffByComponent = $this->staffByComponent($item['components'] ?? []);
        $comboItems = $combo->comboItems;

        if (array_diff(array_keys($staffByComponent), $comboItems->pluck('component_service_id')->all()) !== []) {
            throw new InvalidArgumentException("One of the services does not belong to \"{$combo->name}\".");
        }

        $targetTotal = (string) ($item['unit_price'] ?? $combo->price);
        $componentsTotal = $comboItems->reduce(fn (string $sum, $comboItem): string => bcadd($sum, (string) $comboItem->price, 2), '0');
        $defaultGstRate = $this->tenantContext->get()->default_gst_rate;
        $group = (string) Str::uuid();
        $allocated = '0';
        $lines = [];

        foreach ($comboItems as $index => $comboItem) {
            $component = $comboItem->component;
            $staffProfileId = $staffByComponent[$comboItem->component_service_id] ?? null;

            if ($staffProfileId !== null && ! $component->staff()->where('staff_profiles.id', $staffProfileId)->exists()) {
                throw new InvalidArgumentException("The selected staff member is not eligible to perform \"{$component->name}\".");
            }

            $isLast = $index === $comboItems->count() - 1;

            $price = match (true) {
                $isLast => bcsub($targetTotal, $allocated, 2),
                bccomp($componentsTotal, '0', 2) === 0 => '0.00',
                default => bcadd(bcmul((string) $comboItem->price, bcdiv($targetTotal, $componentsTotal, 10), 6), '0.005', 2),
            };

            $allocated = bcadd($allocated, $price, 2);

            $lines[] = [
                'service_id' => $component->id,
                'combo_service_id' => $combo->id,
                'combo_group' => $group,
                'staff_profile_id' => $staffProfileId,
                'referred_by_staff_profile_id' => $item['referred_by_staff_profile_id'] ?? null,
                'description' => "{$combo->name} - {$component->name}",
                'quantity' => 1,
                'target_amount' => $price,
                'unit_price' => (float) $price,
                'tax_rate' => (float) ($component->tax_rate ?? $defaultGstRate),
            ];
        }

        return $lines;
    }

    /**
     * @param  array<int, array{service_id: int, staff_profile_id: int|null}>  $components
     * @return array<int, int|null>
     */
    private function staffByComponent(array $components): array
    {
        $staffByComponent = [];

        foreach ($components as $component) {
            $staffByComponent[(int) $component['service_id']] = empty($component['staff_profile_id']) ? null : (int) $component['staff_profile_id'];
        }

        return $staffByComponent;
    }
}
