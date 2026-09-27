<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Service;
use App\Models\ServicePriceHistory;
use App\Services\BranchContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServicePriceHistory>
 */
class ServicePriceHistoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $service = Service::factory()->create();

        return [
            'tenant_id' => $service->tenant_id,
            'branch_id' => function (array $attributes) use ($service) {
                if ($attributes['tenant_id'] === $service->tenant_id) {
                    return $service->branch_id;
                }

                $branch = app(BranchContext::class)->get();

                if ($branch && $branch->tenant_id === $attributes['tenant_id']) {
                    return $branch->id;
                }

                return Branch::defaultForTenant($attributes['tenant_id'])->id;
            },
            'service_id' => $service->id,
            'price' => $service->price,
            'effective_from' => now(),
            'changed_by' => null,
        ];
    }
}
