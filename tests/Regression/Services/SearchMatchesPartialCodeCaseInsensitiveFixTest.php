<?php

namespace Tests\Regression\Services;

use App\Models\Service;
use App\Models\Tenant;
use App\Repositories\Eloquent\ServiceRepository;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchMatchesPartialCodeCaseInsensitiveFixTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Bug: Service::scopeSearch() matched 'code' with an exact-equality
     * OR clause and 'name' with a plain 'like', which is case-sensitive on
     * PostgreSQL (the production database, unlike SQLite/MySQL). Typing a
     * partial POS code, or a service name in different casing, on the bill
     * autosuggest returned "no service matching" even though the service
     * existed. Fixed by matching both 'name' and 'code' with a portable
     * LOWER(...) LIKE '%...%' comparison.
     */
    public function test_search_matches_partial_code_regardless_of_case(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);

        Service::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Gents Haircut', 'code' => '101']);
        Service::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Hydra Facial', 'code' => '102']);

        $repository = new ServiceRepository(new Service);

        $this->assertCount(1, $repository->search('101'));
        $this->assertCount(1, $repository->search('haircut'));
        $this->assertCount(1, $repository->search('HAIRCUT'));
        $this->assertCount(0, $repository->search('nonexistent'));
    }
}
