<?php

namespace Tests\Regression\Staff;

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsTenant;
use Tests\TestCase;

class CreateStaffWithHiddenLoginFieldsFixTest extends TestCase
{
    use ActsAsTenant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenant();
        $this->seed(PermissionSeeder::class);
    }

    /**
     * Bug: the create-staff form always renders the username/password inputs
     * (hidden via CSS when "Create a login" is unchecked), so browsers still
     * submit them as empty strings. StoreStaffRequest validated username and
     * password with 'string'/'min:8' but no 'nullable', so an empty string
     * failed those rules even though create_login was false, leaving the
     * user stuck on the form with no visible errors for those hidden fields.
     */
    public function test_empty_hidden_login_fields_do_not_block_staff_creation(): void
    {
        $owner = User::factory()->for($this->tenant)->create();
        $owner->assignRole('Owner');

        $response = $this->actingAs($owner)->postToTenant('/staff', [
            'name' => 'Ravi Kumar',
            'create_login' => '',
            'username' => '',
            'password' => '',
        ]);

        $response->assertSessionDoesntHaveErrors(['create_login', 'username', 'password']);
        $response->assertRedirect();
        $this->assertDatabaseHas('staff_profiles', ['name' => 'Ravi Kumar', 'user_id' => null]);
    }
}
