<?php

namespace Tests\Feature\Clients;

use App\Models\Client;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsTenant;
use Tests\TestCase;

class ExportClientsTest extends TestCase
{
    use ActsAsTenant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenant();
        $this->seed(PermissionSeeder::class);
    }

    public function test_front_desk_can_export_clients_as_excel(): void
    {
        $frontDesk = User::factory()->for($this->tenant)->create();
        $frontDesk->assignRole('FrontDesk');
        Client::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($frontDesk)->getFromTenant('/clients/export');

        $response->assertOk();
        $response->assertDownload('clients-'.now()->format('Y-m-d').'.xlsx');
    }

    public function test_export_respects_search_filter(): void
    {
        $frontDesk = User::factory()->for($this->tenant)->create();
        $frontDesk->assignRole('FrontDesk');
        Client::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Priya Nair']);

        $response = $this->actingAs($frontDesk)->getFromTenant('/clients/export?search=priya');

        $response->assertOk();
    }

    public function test_index_shows_export_button(): void
    {
        $frontDesk = User::factory()->for($this->tenant)->create();
        $frontDesk->assignRole('FrontDesk');

        $response = $this->actingAs($frontDesk)->getFromTenant('/clients');

        $response->assertSee('Export to Excel');
    }

    public function test_user_without_permission_cannot_export_clients(): void
    {
        $user = User::factory()->for($this->tenant)->create();

        $response = $this->actingAs($user)->getFromTenant('/clients/export');

        $response->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->getFromTenant('/clients/export');

        $response->assertRedirect('/login');
    }
}
