<?php

namespace Tests\Feature\SuperAdmin;

use App\Models\PlatformAdmin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsSuperAdmin;
use Tests\TestCase;

class ManagePlatformAdminsTest extends TestCase
{
    use ActsAsSuperAdmin, RefreshDatabase;

    private PlatformAdmin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpMainDomain();
        $this->admin = PlatformAdmin::factory()->create();
    }

    public function test_super_admin_can_view_platform_admins_index(): void
    {
        $response = $this->actingAs($this->admin, 'super_admin')->getFromSuperAdmin('/admins');

        $response->assertOk();
    }

    public function test_super_admin_can_create_another_admin_user(): void
    {
        $response = $this->actingAs($this->admin, 'super_admin')->postToSuperAdmin('/admins', [
            'name' => 'Second Admin',
            'username' => 'second-admin',
            'password' => 'secret',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('platform_admins', ['username' => 'second-admin']);
    }

    public function test_username_must_be_unique_across_admins(): void
    {
        PlatformAdmin::factory()->create(['username' => 'dup']);

        $response = $this->actingAs($this->admin, 'super_admin')->postToSuperAdmin('/admins', [
            'name' => 'Dup Admin',
            'username' => 'dup',
            'password' => 'secret',
        ]);

        $response->assertSessionHasErrors('username');
    }

    public function test_super_admin_can_update_another_admin_without_changing_password(): void
    {
        $other = PlatformAdmin::factory()->create();
        $originalPassword = $other->password;

        $response = $this->actingAs($this->admin, 'super_admin')->putToSuperAdmin("/admins/{$other->id}", [
            'name' => 'Renamed Admin',
            'username' => $other->username,
        ]);

        $response->assertRedirect();
        $other->refresh();
        $this->assertSame('Renamed Admin', $other->name);
        $this->assertSame($originalPassword, $other->password);
    }

    public function test_super_admin_can_delete_another_admin(): void
    {
        $other = PlatformAdmin::factory()->create();

        $response = $this->actingAs($this->admin, 'super_admin')->deleteFromSuperAdmin("/admins/{$other->id}");

        $response->assertRedirect();
        $this->assertDatabaseMissing('platform_admins', ['id' => $other->id]);
    }

    public function test_super_admin_cannot_delete_their_own_account(): void
    {
        $response = $this->actingAs($this->admin, 'super_admin')->deleteFromSuperAdmin("/admins/{$this->admin->id}");

        $response->assertForbidden();
        $this->assertDatabaseHas('platform_admins', ['id' => $this->admin->id]);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->getFromSuperAdmin('/admins');

        $response->assertRedirect($this->superAdminUrl('/login'));
    }
}
