<?php

namespace Tests\Feature\SuperAdmin;

use App\Models\PlatformAdmin;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Concerns\ActsAsSuperAdmin;
use Tests\TestCase;

class ManageTenantsTest extends TestCase
{
    use ActsAsSuperAdmin, RefreshDatabase;

    private PlatformAdmin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpMainDomain();
        $this->admin = PlatformAdmin::factory()->create();
    }

    public function test_super_admin_can_view_tenants_index(): void
    {
        Tenant::factory()->count(2)->create();

        $response = $this->actingAs($this->admin, 'super_admin')->getFromSuperAdmin('/tenants');

        $response->assertOk();
    }

    public function test_super_admin_can_create_a_tenant(): void
    {
        $response = $this->actingAs($this->admin, 'super_admin')->postToSuperAdmin('/tenants', [
            'name' => 'New Salon',
            'slug' => 'new-salon',
            'subdomain' => 'new-salon',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('tenants', [
            'name' => 'New Salon',
            'slug' => 'new-salon',
            'subdomain' => 'new-salon',
        ]);
    }

    public function test_creating_a_tenant_requires_a_unique_subdomain(): void
    {
        Tenant::factory()->create(['subdomain' => 'taken']);

        $response = $this->actingAs($this->admin, 'super_admin')->postToSuperAdmin('/tenants', [
            'name' => 'New Salon',
            'slug' => 'new-salon',
            'subdomain' => 'taken',
        ]);

        $response->assertSessionHasErrors('subdomain');
    }

    public function test_super_admin_can_update_a_tenant_name_and_logos(): void
    {
        $tenant = Tenant::factory()->create();

        $response = $this->actingAs($this->admin, 'super_admin')->putToSuperAdmin("/tenants/{$tenant->id}", [
            'name' => 'Renamed Salon',
            'print_logo' => UploadedFile::fake()->image('print.png', 10, 10)->size(5),
            'ui_logo' => UploadedFile::fake()->image('ui.png', 10, 10)->size(5),
        ]);

        $response->assertRedirect();
        $tenant->refresh();
        $this->assertSame('Renamed Salon', $tenant->name);
        $this->assertNotNull($tenant->print_logo);
        $this->assertNotNull($tenant->ui_logo);
        $this->assertStringStartsWith('data:image/png;base64,', $tenant->print_logo);
    }

    public function test_super_admin_can_remove_a_tenant_logo(): void
    {
        $tenant = Tenant::factory()->create(['print_logo' => 'data:image/png;base64,abc123']);

        $response = $this->actingAs($this->admin, 'super_admin')->putToSuperAdmin("/tenants/{$tenant->id}", [
            'name' => $tenant->name,
            'remove_print_logo' => '1',
        ]);

        $response->assertRedirect();
        $this->assertNull($tenant->refresh()->print_logo);
    }

    public function test_super_admin_can_toggle_tenant_active_status(): void
    {
        $tenant = Tenant::factory()->create(['is_active' => true]);

        $response = $this->actingAs($this->admin, 'super_admin')->deleteFromSuperAdmin("/tenants/{$tenant->id}");

        $response->assertRedirect();
        $this->assertFalse($tenant->refresh()->is_active);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->getFromSuperAdmin('/tenants');

        $response->assertRedirect($this->superAdminUrl('/login'));
    }
}
