<?php

namespace Tests\Unit\Commission;

use App\Models\Branch;
use App\Models\CommissionRate;
use App\Models\ServiceCategory;
use App\Models\StaffProfile;
use App\Models\Tenant;
use App\Repositories\Eloquent\CommissionRateRepository;
use App\Services\BranchContext;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommissionRateRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_all_returns_rates_ordered_by_effective_from_descending(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);

        CommissionRate::factory()->create(['tenant_id' => $tenant->id, 'rate_percent' => 10, 'effective_from' => '2026-01-01']);
        CommissionRate::factory()->create(['tenant_id' => $tenant->id, 'rate_percent' => 15, 'effective_from' => '2026-06-01']);

        $repository = app(CommissionRateRepository::class);

        $rates = $repository->getAll()->pluck('rate_percent')->map(fn ($rate) => (string) $rate)->all();

        $this->assertSame(['15.00', '10.00'], $rates);
    }

    public function test_create_persists_a_new_rate(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);
        $staff = StaffProfile::factory()->create(['tenant_id' => $tenant->id]);
        $category = ServiceCategory::factory()->create(['tenant_id' => $tenant->id]);

        $repository = app(CommissionRateRepository::class);

        $rate = $repository->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'staff_profile_id' => $staff->id,
            'service_category_id' => $category->id,
            'rate_percent' => 20,
            'effective_from' => '2026-01-01',
        ]);

        $this->assertDatabaseHas('commission_rates', [
            'id' => $rate->id,
            'staff_profile_id' => $staff->id,
            'service_category_id' => $category->id,
        ]);
    }

    public function test_update_modifies_the_existing_rate(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);
        $rate = CommissionRate::factory()->create(['tenant_id' => $tenant->id, 'rate_percent' => 10]);

        $repository = app(CommissionRateRepository::class);
        $repository->update($rate, ['rate_percent' => 22]);

        $this->assertSame('22.00', (string) $rate->fresh()->rate_percent);
    }

    public function test_delete_soft_deletes_the_rate(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);
        $rate = CommissionRate::factory()->create(['tenant_id' => $tenant->id]);

        $repository = app(CommissionRateRepository::class);
        $repository->delete($rate);

        $this->assertSoftDeleted('commission_rates', ['id' => $rate->id]);
    }
}
