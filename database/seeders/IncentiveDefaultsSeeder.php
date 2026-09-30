<?php

namespace Database\Seeders;

use App\Models\IncentiveSetting;
use App\Models\IncentiveSlab;
use App\Models\Tenant;
use App\Services\TenantContext;
use Illuminate\Database\Seeder;

class IncentiveDefaultsSeeder extends Seeder
{
    /** @var array<int, array{min: int, incentive: int}> */
    public const DefaultSlabs = [
        ['min' => 80, 'incentive' => 3],
        ['min' => 90, 'incentive' => 4],
        ['min' => 100, 'incentive' => 5],
    ];

    public function run(): void
    {
        $tenantContext = app(TenantContext::class);

        Tenant::query()->each(function (Tenant $tenant) use ($tenantContext): void {
            $tenantContext->set($tenant);

            IncentiveSetting::firstOrCreate(
                ['tenant_id' => $tenant->id],
                ['servicing_share_percent' => 70, 'referring_share_percent' => 30],
            );

            if (IncentiveSlab::query()->exists()) {
                return;
            }

            foreach (self::DefaultSlabs as $slab) {
                IncentiveSlab::create([
                    'tenant_id' => $tenant->id,
                    'min_achievement_percent' => $slab['min'],
                    'incentive_percent' => $slab['incentive'],
                ]);
            }
        });
    }
}
