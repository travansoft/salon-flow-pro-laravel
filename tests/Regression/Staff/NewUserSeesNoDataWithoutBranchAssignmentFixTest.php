<?php

namespace Tests\Regression\Staff;

use App\Models\Branch;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsTenant;
use Tests\TestCase;

class NewUserSeesNoDataWithoutBranchAssignmentFixTest extends TestCase
{
    use ActsAsTenant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenant();
        $this->seed(PermissionSeeder::class);
    }

    /**
     * Bug: StoreStaffRequest allowed branch_ids to be submitted empty even
     * when create_login was checked, so a new user could be created with no
     * rows in user_branch. ResolveBranch then marks the branch context as
     * "none resolved" for that user, and BranchScope blanket-filters every
     * branch-scoped query (services, appointments, bills, ...) to zero rows
     * on every subsequent login — while the tenant's other users, who already
     * had a branch assigned, kept seeing data normally.
     */
    public function test_creating_a_staff_login_without_selecting_a_branch_is_rejected(): void
    {
        $owner = User::factory()->for($this->tenant)->create();
        $owner->assignRole('Owner');

        $response = $this->actingAs($owner)->postToTenant('/staff', [
            'name' => 'New Hire',
            'create_login' => '1',
            'username' => 'newhire',
            'password' => 'password123',
            'roles' => ['Stylist'],
        ]);

        $response->assertSessionHasErrors('branch_ids');
        $this->assertDatabaseMissing('users', ['username' => 'newhire']);
    }

    public function test_new_staff_login_with_an_assigned_branch_can_see_branch_scoped_data(): void
    {
        $owner = User::factory()->for($this->tenant)->create();
        $owner->assignRole('Owner');
        $branch = Branch::factory()->create(['tenant_id' => $this->tenant->id]);
        $service = Service::factory()->create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $branch->id,
            'name' => 'Signature Haircut',
        ]);

        $response = $this->actingAs($owner)->postToTenant('/staff', [
            'name' => 'New Hire',
            'create_login' => '1',
            'username' => 'newhire',
            'password' => 'password123',
            'roles' => ['Owner'],
            'branch_ids' => [$branch->id],
        ]);
        $response->assertRedirect();

        $newUser = User::where('username', 'newhire')->firstOrFail();
        $this->assertTrue($newUser->branches()->where('branches.id', $branch->id)->exists());

        $servicesResponse = $this->actingAs($newUser)->getFromTenant('/services');

        $servicesResponse->assertOk();
        $servicesResponse->assertSee($service->name);
    }
}
