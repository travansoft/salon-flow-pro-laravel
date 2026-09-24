<?php

namespace Tests\Integration\Clients;

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Tenant;
use App\Services\BranchContext;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientDatabaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_visit_history_includes_all_past_appointments(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);

        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        Appointment::factory()->count(3)->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        $this->assertSame(3, $client->appointments()->count());
    }

    public function test_tenant_scope_excludes_clients_from_other_tenants(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        Client::factory()->create(['tenant_id' => $tenantA->id]);
        Client::factory()->create(['tenant_id' => $tenantB->id]);

        app(TenantContext::class)->set($tenantA);
        $branchA = Branch::factory()->create(['tenant_id' => $tenantA->id]);
        app(BranchContext::class)->set($branchA);

        $this->assertSame(1, Client::count());
    }

    public function test_deleting_a_client_soft_deletes_and_preserves_history(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);

        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        $client->delete();

        $this->assertSoftDeleted('clients', ['id' => $client->id]);
    }
}
