<?php

namespace Tests\Regression\Commission;

use App\Models\Branch;
use App\Models\CommissionRate;
use App\Models\ServiceCategory;
use App\Models\StaffProfile;
use App\Models\Tenant;
use App\Services\BranchContext;
use App\Services\CommissionService;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class StaffSpecificRateWinsOverNewerCategoryRateFixTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Bug: resolveRateFor() naively picked whichever matching rate had the
     * most recent effective_from date, regardless of specificity. That meant
     * a broad, all-staff category rate created (or re-dated) after a staff
     * member's personal override would incorrectly outrank the personal
     * rate, even though the personal rate is strictly more specific. Fixed
     * by resolving specificity tier first (exact staff+category, then
     * staff-only, then category-only, then tenant default) and only using
     * effective_from to break ties within the same tier.
     */
    public function test_staff_specific_rate_wins_even_when_category_wide_rate_is_more_recently_effective(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);
        $staff = StaffProfile::factory()->create(['tenant_id' => $tenant->id]);
        $category = ServiceCategory::factory()->create(['tenant_id' => $tenant->id]);

        CommissionRate::factory()->create([
            'tenant_id' => $tenant->id,
            'staff_profile_id' => $staff->id,
            'service_category_id' => null,
            'rate_percent' => 20,
            'effective_from' => '2026-01-01',
        ]);

        CommissionRate::factory()->create([
            'tenant_id' => $tenant->id,
            'staff_profile_id' => null,
            'service_category_id' => $category->id,
            'rate_percent' => 12,
            'effective_from' => '2026-06-01',
        ]);

        $rate = app(CommissionService::class)->resolveRateFor($staff, $category->id, Carbon::parse('2026-06-15'));

        $this->assertSame('20.00', $rate);
    }
}
