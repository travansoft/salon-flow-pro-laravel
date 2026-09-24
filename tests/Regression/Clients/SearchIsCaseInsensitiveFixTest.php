<?php

namespace Tests\Regression\Clients;

use App\Models\Branch;
use App\Models\Client;
use App\Models\Tenant;
use App\Repositories\Eloquent\ClientRepository;
use App\Services\BranchContext;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchIsCaseInsensitiveFixTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Bug: Client::scopeSearch() matched 'name' and 'phone' with a plain
     * 'like', which is case-sensitive on PostgreSQL (the production
     * database). Typing a client name in different casing on the bill
     * autosuggest returned no matches even though the client existed.
     * Fixed with a portable LOWER(...) LIKE '%...%' comparison.
     */
    public function test_search_matches_name_regardless_of_case(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);

        Client::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Priya Nair', 'phone' => '9000000001']);

        $repository = new ClientRepository(new Client);

        $this->assertCount(1, $repository->search('priya'));
        $this->assertCount(1, $repository->search('PRIYA'));
    }
}
