<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsTenant;
use Tests\TestCase;

class TenantStaffAvailabilitySettingTest extends TestCase
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

    public function test_staff_availability_check_is_off_by_default(): void
    {
        $this->assertFalse($this->tenant->fresh()->check_staff_availability);
    }

    public function test_owner_can_enable_staff_availability_check(): void
    {
        $owner = $this->userWithRole('Owner');

        $response = $this->actingAs($owner)->putToTenant('/settings', ['check_staff_availability' => 1]);

        $response->assertRedirect();
        $this->assertDatabaseHas('tenants', ['id' => $this->tenant->id, 'check_staff_availability' => true]);
    }

    public function test_invalid_value_is_rejected(): void
    {
        $owner = $this->userWithRole('Owner');

        $response = $this->actingAs($owner)->putToTenant('/settings', ['check_staff_availability' => 'maybe']);

        $response->assertSessionHasErrors('check_staff_availability');
    }

    public function test_stylist_cannot_change_staff_availability_check(): void
    {
        $stylist = $this->userWithRole('Stylist');

        $response = $this->actingAs($stylist)->putToTenant('/settings', ['check_staff_availability' => 1]);

        $response->assertForbidden();
        $this->assertDatabaseHas('tenants', ['id' => $this->tenant->id, 'check_staff_availability' => false]);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->putToTenant('/settings', ['check_staff_availability' => 1]);

        $response->assertRedirect('/login');
    }
}
