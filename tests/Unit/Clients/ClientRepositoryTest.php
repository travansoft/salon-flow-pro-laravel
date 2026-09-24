<?php

namespace Tests\Unit\Clients;

use App\Models\Branch;
use App\Models\Client;
use App\Models\Tenant;
use App\Repositories\Eloquent\ClientRepository;
use App\Services\BranchContext;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_matches_by_name_or_phone(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);

        Client::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Priya Nair', 'phone' => '9000000001']);
        Client::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Anjali Menon', 'phone' => '9000000002']);

        $repository = new ClientRepository(new Client);

        $this->assertCount(1, $repository->search('Priya'));
        $this->assertCount(1, $repository->search('9000000002'));
        $this->assertCount(0, $repository->search('nonexistent'));
    }

    public function test_create_persists_a_new_client(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);

        $repository = new ClientRepository(new Client);

        $client = $repository->create([
            'tenant_id' => $tenant->id,
            'name' => 'Priya Nair',
            'phone' => '9000000001',
        ]);

        $this->assertDatabaseHas('clients', ['id' => $client->id, 'name' => 'Priya Nair']);
    }
}
