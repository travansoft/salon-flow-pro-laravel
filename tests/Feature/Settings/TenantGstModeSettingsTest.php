<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsTenant;
use Tests\TestCase;

class TenantGstModeSettingsTest extends TestCase
{
    use ActsAsTenant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenant();
        $this->setUpBranch();
        $this->seed(PermissionSeeder::class);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->for($this->tenant)->create();
        $user->assignRole($role);
        $this->assignToBranch($user);

        return $user;
    }

    public function test_owner_can_switch_gst_to_additional(): void
    {
        $owner = $this->userWithRole('Owner');

        $response = $this->actingAs($owner)->putToTenant('/settings', ['gst_inclusive' => 0, 'default_gst_rate' => 12]);

        $response->assertRedirect();
        $this->assertDatabaseHas('tenants', ['id' => $this->tenant->id, 'gst_inclusive' => false, 'default_gst_rate' => 12]);
    }

    public function test_owner_can_switch_gst_back_to_inclusive(): void
    {
        $this->tenant->update(['gst_inclusive' => false]);
        $owner = $this->userWithRole('Owner');

        $this->actingAs($owner)->putToTenant('/settings', ['gst_inclusive' => 1]);

        $this->assertDatabaseHas('tenants', ['id' => $this->tenant->id, 'gst_inclusive' => true]);
    }

    public function test_invalid_gst_mode_is_rejected(): void
    {
        $owner = $this->userWithRole('Owner');

        $response = $this->actingAs($owner)->putToTenant('/settings', ['gst_inclusive' => 'maybe']);

        $response->assertSessionHasErrors('gst_inclusive');
    }

    public function test_settings_page_shows_the_current_gst_mode(): void
    {
        $this->tenant->update(['gst_inclusive' => false]);
        $owner = $this->userWithRole('Owner');

        $response = $this->actingAs($owner)->getFromTenant('/settings');

        $response->assertOk()->assertSee('Additional to the service price');
    }

    public function test_new_bill_page_shows_gst_row_only_when_gst_is_additional(): void
    {
        $owner = $this->userWithRole('Owner');

        $this->actingAs($owner)->getFromTenant('/bills/create')->assertOk()->assertDontSee('GST (added on top)');

        $this->tenant->update(['gst_inclusive' => false]);

        $this->actingAs($owner)->getFromTenant('/bills/create')->assertOk()->assertSee('GST (added on top)');
    }

    public function test_stylist_cannot_change_gst_mode(): void
    {
        $stylist = $this->userWithRole('Stylist');

        $response = $this->actingAs($stylist)->putToTenant('/settings', ['gst_inclusive' => 0]);

        $response->assertForbidden();
        $this->assertDatabaseHas('tenants', ['id' => $this->tenant->id, 'gst_inclusive' => true]);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->putToTenant('/settings', ['gst_inclusive' => 0]);

        $response->assertRedirect('/login');
    }
}
