<?php

namespace Tests\Feature\Appointments;

use App\Models\Service;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsTenant;
use Tests\TestCase;

class ServiceSearchTest extends TestCase
{
    use ActsAsTenant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenant();
        $this->seed(PermissionSeeder::class);
    }

    public function test_searching_by_name_returns_matching_active_services(): void
    {
        $frontDesk = User::factory()->for($this->tenant)->create();
        $frontDesk->assignRole('FrontDesk');
        Service::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Haircut', 'is_active' => true]);
        Service::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Manicure', 'is_active' => true]);

        $response = $this->actingAs($frontDesk)->getFromTenant('/appointments/services/search?q=Hair');

        $response->assertOk();
        $response->assertJsonCount(1, 'services');
        $response->assertJsonFragment(['name' => 'Haircut']);
    }

    public function test_blank_query_returns_active_services(): void
    {
        $frontDesk = User::factory()->for($this->tenant)->create();
        $frontDesk->assignRole('FrontDesk');
        Service::factory()->create(['tenant_id' => $this->tenant->id, 'is_active' => true]);
        Service::factory()->create(['tenant_id' => $this->tenant->id, 'is_active' => false]);

        $response = $this->actingAs($frontDesk)->getFromTenant('/appointments/services/search?q=');

        $response->assertOk();
        $response->assertJsonCount(1, 'services');
    }

    public function test_stylist_cannot_search_services(): void
    {
        $stylist = User::factory()->for($this->tenant)->create();
        $stylist->assignRole('Stylist');

        $response = $this->actingAs($stylist)->getFromTenant('/appointments/services/search?q=Hair');

        $response->assertForbidden();
    }
}
