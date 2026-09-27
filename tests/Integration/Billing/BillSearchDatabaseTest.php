<?php

namespace Tests\Integration\Billing;

use App\Models\Bill;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Tenant;
use App\Repositories\Contracts\BillRepositoryInterface;
use App\Services\BranchContext;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillSearchDatabaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_filters_by_date_range_and_client_name_and_phone(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);

        $client = Client::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Divya Menon', 'phone' => '9876543210']);
        $otherClient = Client::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Sarath Kumar', 'phone' => '9123456780']);

        $matching = Bill::factory()->create([
            'tenant_id' => $tenant->id,
            'client_id' => $client->id,
            'created_at' => '2026-01-10',
        ]);
        Bill::factory()->create([
            'tenant_id' => $tenant->id,
            'client_id' => $otherClient->id,
            'created_at' => '2026-01-10',
        ]);
        Bill::factory()->create([
            'tenant_id' => $tenant->id,
            'client_id' => $client->id,
            'created_at' => '2026-02-01',
        ]);

        $results = app(BillRepositoryInterface::class)->search('2026-01-01', '2026-01-31', 'divya', '98765');

        $this->assertCount(1, $results);
        $this->assertTrue($results->first()->is($matching));
    }
}
