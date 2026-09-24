<?php

namespace Tests\Unit;

use App\Exceptions\NoTenantContextException;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Tenant;
use App\Services\BranchContext;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_query_throws_when_no_tenant_context_and_none_resolved_was_not_marked(): void
    {
        $this->expectException(NoTenantContextException::class);

        Client::query()->count();
    }

    public function test_query_returns_empty_when_tenant_resolution_ran_and_found_none(): void
    {
        app(TenantContext::class)->markNoneResolved();

        $this->assertSame(0, Client::query()->count());
    }

    public function test_query_scopes_to_the_active_tenant_when_one_is_set(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        app(TenantContext::class)->set($tenantA);
        $branchA = Branch::factory()->create(['tenant_id' => $tenantA->id]);
        app(BranchContext::class)->set($branchA);
        Client::factory()->create(['tenant_id' => $tenantA->id]);
        Client::factory()->create(['tenant_id' => $tenantB->id]);

        $this->assertSame(1, Client::query()->count());
    }

    public function test_bypass_returns_all_tenants_data(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        Client::factory()->create(['tenant_id' => $tenantA->id]);
        Client::factory()->create(['tenant_id' => $tenantB->id]);

        app(TenantContext::class)->bypass();

        $this->assertSame(2, Client::query()->count());
    }
}
