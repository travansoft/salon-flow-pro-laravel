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

    public function test_creating_a_tenant_with_a_reserved_subdomain_is_rejected(): void
    {
        $response = $this->actingAs($this->admin, 'super_admin')->postToSuperAdmin('/tenants', [
            'name' => 'New Salon',
            'slug' => 'new-salon',
            'subdomain' => 'admin',
        ]);

        $response->assertSessionHasErrors('subdomain');
        $this->assertDatabaseMissing('tenants', ['slug' => 'new-salon']);
    }

    public function test_super_admin_can_update_a_tenant_name_and_logos(): void
    {
        $tenant = Tenant::factory()->create();

        $response = $this->actingAs($this->admin, 'super_admin')->putToSuperAdmin("/tenants/{$tenant->id}", [
            'name' => 'Renamed Salon',
            'slug' => $tenant->slug,
            'subdomain' => $tenant->subdomain,
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
            'slug' => $tenant->slug,
            'subdomain' => $tenant->subdomain,
            'remove_print_logo' => '1',
        ]);

        $response->assertRedirect();
        $this->assertNull($tenant->refresh()->print_logo);
    }

    public function test_super_admin_can_update_a_tenants_slug_and_subdomain(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'old-slug', 'subdomain' => 'old-sub']);

        $response = $this->actingAs($this->admin, 'super_admin')->putToSuperAdmin("/tenants/{$tenant->id}", [
            'name' => $tenant->name,
            'slug' => 'new-slug',
            'subdomain' => 'new-sub',
        ]);

        $response->assertRedirect();
        $tenant->refresh();
        $this->assertSame('new-slug', $tenant->slug);
        $this->assertSame('new-sub', $tenant->subdomain);
    }

    public function test_updating_slug_requires_it_to_stay_unique(): void
    {
        Tenant::factory()->create(['slug' => 'taken-slug']);
        $tenant = Tenant::factory()->create(['slug' => 'mine']);

        $response = $this->actingAs($this->admin, 'super_admin')->putToSuperAdmin("/tenants/{$tenant->id}", [
            'name' => $tenant->name,
            'slug' => 'taken-slug',
            'subdomain' => $tenant->subdomain,
        ]);

        $response->assertSessionHasErrors('slug');
        $this->assertSame('mine', $tenant->refresh()->slug);
    }

    public function test_updating_subdomain_to_a_reserved_word_is_rejected(): void
    {
        $tenant = Tenant::factory()->create();

        $response = $this->actingAs($this->admin, 'super_admin')->putToSuperAdmin("/tenants/{$tenant->id}", [
            'name' => $tenant->name,
            'slug' => $tenant->slug,
            'subdomain' => 'admin',
        ]);

        $response->assertSessionHasErrors('subdomain');
    }

    public function test_updating_slug_to_a_reserved_word_is_rejected(): void
    {
        $tenant = Tenant::factory()->create();

        $response = $this->actingAs($this->admin, 'super_admin')->putToSuperAdmin("/tenants/{$tenant->id}", [
            'name' => $tenant->name,
            'slug' => 'login',
            'subdomain' => $tenant->subdomain,
        ]);

        $response->assertSessionHasErrors('slug');
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
