<?php

namespace Tests\Integration\SuperAdmin;

use App\Models\Tenant;
use App\Repositories\Eloquent\TenantRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_matches_by_name_slug_or_subdomain_case_insensitively(): void
    {
        $target = Tenant::factory()->create(['name' => 'Glow Studio', 'slug' => 'glow-studio', 'subdomain' => 'glow-studio']);
        Tenant::factory()->create(['name' => 'Shine Salon', 'slug' => 'shine-salon', 'subdomain' => 'shine-salon']);

        $repository = new TenantRepository(new Tenant);
        $results = $repository->search('GLOW');

        $this->assertCount(1, $results);
        $this->assertSame($target->id, $results->first()->id);
    }

    public function test_search_with_no_matches_returns_empty_collection(): void
    {
        Tenant::factory()->create(['name' => 'Glow Studio']);

        $repository = new TenantRepository(new Tenant);
        $results = $repository->search('nonexistent');

        $this->assertCount(0, $results);
    }
}
