<?php

namespace Tests\Integration\SuperAdmin;

use App\Models\Tenant;
use App\Models\User;
use App\Repositories\Eloquent\TenantUserRepository;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantUserSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(TenantContext::class)->bypass();
    }

    public function test_search_in_tenant_matches_by_name_username_or_email(): void
    {
        $tenant = Tenant::factory()->create();
        $target = User::factory()->for($tenant)->create(['name' => 'Priya Owner', 'username' => 'priya', 'email' => 'priya@example.com']);
        User::factory()->for($tenant)->create(['name' => 'Anita Stylist', 'username' => 'anita']);

        $repository = new TenantUserRepository(new User);
        $results = $repository->searchInTenant($tenant, 'priya');

        $this->assertCount(1, $results);
        $this->assertSame($target->id, $results->first()->id);
    }

    public function test_search_in_tenant_does_not_match_users_from_other_tenants(): void
    {
        $tenant = Tenant::factory()->create();
        $otherTenant = Tenant::factory()->create();
        User::factory()->for($otherTenant)->create(['name' => 'Priya Owner', 'username' => 'priya']);

        $repository = new TenantUserRepository(new User);
        $results = $repository->searchInTenant($tenant, 'priya');

        $this->assertCount(0, $results);
    }
}
