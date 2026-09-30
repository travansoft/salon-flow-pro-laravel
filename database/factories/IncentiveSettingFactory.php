<?php

namespace Database\Factories;

use App\Models\IncentiveSetting;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IncentiveSetting>
 */
class IncentiveSettingFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'tenant_id' => fn () => Tenant::factory()->create()->id,
            'servicing_share_percent' => 70,
            'referring_share_percent' => 30,
        ];
    }
}
