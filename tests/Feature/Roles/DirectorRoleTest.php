<?php

namespace Tests\Feature\Roles;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsTenant;
use Tests\TestCase;

class DirectorRoleTest extends TestCase
{
    use ActsAsTenant, RefreshDatabase;

    private User $viewer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenant();
        $this->setUpBranch();
        $this->seed(PermissionSeeder::class);

        $this->viewer = User::factory()->for($this->tenant)->create();
        $this->viewer->assignRole('Director');
        $this->assignToBranch($this->viewer);
    }

    public function test_director_only_holds_view_permissions(): void
    {
        $permissions = Role::where('name', 'Director')->first()->permissions->pluck('name');

        $nonReadOnly = $permissions->reject(fn (string $name): bool => str_ends_with($name, '.view'));

        $this->assertSame([], $nonReadOnly->values()->all());
        $this->assertTrue($permissions->contains('dashboard.view'));
    }

    public function test_director_can_open_dashboard_and_read_only_pages(): void
    {
        foreach (['/dashboard', '/bills', '/expenses', '/products', '/clients', '/staff', '/services', '/branches'] as $uri) {
            $this->actingAs($this->viewer)->getFromTenant($uri)->assertOk();
        }
    }

    public function test_director_cannot_open_any_create_screen(): void
    {
        foreach (['/bills/create', '/expenses/create', '/products/create', '/clients/create', '/staff/create', '/services/create', '/branches/create', '/appointments/create'] as $uri) {
            $this->actingAs($this->viewer)->getFromTenant($uri)->assertForbidden();
        }
    }

    public function test_director_cannot_change_or_remove_data(): void
    {
        $this->actingAs($this->viewer)->postToTenant('/clients', ['name' => 'Nope'])->assertForbidden();
        $this->actingAs($this->viewer)->putToTenant('/settings', ['name' => 'Nope'])->assertForbidden();
        $this->actingAs($this->viewer)->getFromTenant('/settings')->assertForbidden();
    }

    public function test_director_does_not_see_add_buttons_in_the_navigation(): void
    {
        $response = $this->actingAs($this->viewer)->getFromTenant('/dashboard');

        $response->assertDontSee('+ New bill')->assertDontSee('+ New appointment');
    }
}
