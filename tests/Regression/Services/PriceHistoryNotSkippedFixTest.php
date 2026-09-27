<?php

namespace Tests\Regression\Services;

use App\Models\Branch;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Repositories\Contracts\ServiceRepositoryInterface;
use App\Services\BranchContext;
use App\Services\ServiceCatalogService;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class PriceHistoryNotSkippedFixTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Bug risk: comparing the incoming price to the current price with a
     * loose PHP comparison (e.g. != on a decimal-cast string vs a numeric
     * request value) can report "unchanged" for values that are actually
     * different in representation, silently skipping the price-history
     * write. Since bills must keep the price charged at the time, a missed
     * history entry breaks that guarantee. ServiceCatalogService::update()
     * uses bccomp() on stringified values specifically to avoid this.
     */
    public function test_updating_price_from_a_decimal_cast_string_still_records_history(): void
    {
        $tenant = Tenant::factory()->create();
        $tenantContext = app(TenantContext::class);
        $tenantContext->set($tenant);
        $branchContext = app(BranchContext::class);
        $branchContext->set(Branch::factory()->create(['tenant_id' => $tenant->id]));
        $owner = User::factory()->for($tenant)->create();

        $existingService = Service::factory()->create(['tenant_id' => $tenant->id, 'price' => 499]);

        $repository = Mockery::mock(ServiceRepositoryInterface::class);
        $repository->shouldReceive('update')
            ->once()
            ->andReturnUsing(function ($svc, array $data) {
                $svc->update($data);

                return $svc;
            });

        $service = new ServiceCatalogService($repository, $tenantContext, $branchContext);

        $service->update($existingService, ['price' => '499.01'], changedBy: $owner->id);

        $this->assertSame(1, $existingService->priceHistories()->count());
    }

    public function test_updating_to_the_same_price_in_a_different_numeric_form_does_not_record_history(): void
    {
        $tenant = Tenant::factory()->create();
        $tenantContext = app(TenantContext::class);
        $tenantContext->set($tenant);
        $branchContext = app(BranchContext::class);
        $branchContext->set(Branch::factory()->create(['tenant_id' => $tenant->id]));
        $owner = User::factory()->for($tenant)->create();

        $existingService = Service::factory()->create(['tenant_id' => $tenant->id, 'price' => 500]);

        $repository = Mockery::mock(ServiceRepositoryInterface::class);
        $repository->shouldReceive('update')
            ->once()
            ->andReturnUsing(function ($svc, array $data) {
                $svc->update($data);

                return $svc;
            });

        $service = new ServiceCatalogService($repository, $tenantContext, $branchContext);

        $service->update($existingService, ['price' => '500.00'], changedBy: $owner->id);

        $this->assertSame(0, $existingService->priceHistories()->count());
    }
}
