<?php

namespace Tests\Feature\Tenants;

use App\Models\Branch;
use App\Models\PlatformAdmin;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsSuperAdmin;
use Tests\Concerns\ActsAsTenant;
use Tests\TestCase;

class TenantLimitsTest extends TestCase
{
    use ActsAsSuperAdmin, ActsAsTenant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenant();
        $this->seed(PermissionSeeder::class);
    }

    private function owner(): User
    {
        $owner = User::factory()->for($this->tenant)->create();
        $owner->assignRole('Owner');

        return $owner;
    }

    /** @return array<string, string> */
    private function branchPayload(string $slug = 'second'): array
    {
        return ['name' => 'Second', 'slug' => $slug, 'invoice_prefix' => strtoupper($slug)];
    }

    public function test_extra_branch_is_rejected_with_the_default_limit_of_one(): void
    {
        $response = $this->actingAs($this->owner())->postToTenant('/branches', $this->branchPayload());

        $response->assertSessionHasErrors('limit');
        $this->assertDatabaseMissing('branches', ['slug' => 'second']);
    }

    public function test_second_branch_is_allowed_when_limit_is_raised(): void
    {
        $this->tenant->update(['max_branches' => 2]);

        $this->actingAs($this->owner())->postToTenant('/branches', $this->branchPayload())->assertSessionHasNoErrors();

        $this->assertDatabaseHas('branches', ['slug' => 'second']);
    }

    public function test_reactivating_a_branch_beyond_the_limit_is_rejected(): void
    {
        $owner = $this->owner();
        $disabled = Branch::factory()->create(['tenant_id' => $this->tenant->id, 'is_active' => false]);

        $response = $this->actingAs($owner)->putToTenant("/branches/{$disabled->id}", [
            'name' => $disabled->name,
            'slug' => $disabled->slug,
            'invoice_prefix' => $disabled->invoice_prefix,
            'is_active' => 1,
        ]);

        $response->assertSessionHasErrors('limit');
        $this->assertFalse($disabled->fresh()->is_active);
    }

    public function test_config_default_limits_apply_to_every_tenant(): void
    {
        config(['tenancy.limits.max_branches' => 2]);

        $this->actingAs($this->owner())->postToTenant('/branches', $this->branchPayload())->assertSessionHasNoErrors();

        $this->assertDatabaseHas('branches', ['slug' => 'second']);
    }

    public function test_staff_login_beyond_user_limit_is_rejected(): void
    {
        $this->setUpBranch();
        $owner = $this->owner();
        $this->assignToBranch($owner);
        User::factory()->for($this->tenant)->count(4)->create();

        $response = $this->actingAs($owner)->postToTenant('/staff', [
            'name' => 'New Stylist',
            'create_login' => 1,
            'username' => 'newstylist',
            'password' => 'password123',
            'roles' => ['Stylist'],
            'branch_ids' => [$this->branch->id],
        ]);

        $response->assertSessionHasErrors('limit');
        $this->assertDatabaseMissing('users', ['username' => 'newstylist']);
        $this->assertDatabaseMissing('staff_profiles', ['name' => 'New Stylist']);
    }

    public function test_super_admin_can_set_and_see_tenant_limits(): void
    {
        $admin = PlatformAdmin::factory()->create();
        $tenant = Tenant::factory()->create();

        $this->actingAs($admin, 'super_admin')->putToSuperAdmin("/tenants/{$tenant->id}", [
            'name' => $tenant->name,
            'slug' => $tenant->slug,
            'subdomain' => $tenant->subdomain,
            'is_active' => 1,
            'max_users' => 12,
            'max_branches' => 3,
        ])->assertRedirect();

        $tenant->refresh();
        $this->assertSame(12, $tenant->max_users);
        $this->assertSame(3, $tenant->max_branches);
        $this->actingAs($admin, 'super_admin')->getFromSuperAdmin("/tenants/{$tenant->id}")->assertSee('of 12')->assertSee('of 3');
    }

    public function test_blank_super_admin_limits_fall_back_to_defaults(): void
    {
        $admin = PlatformAdmin::factory()->create();
        $tenant = Tenant::factory()->create(['max_users' => 12, 'max_branches' => 3]);

        $this->actingAs($admin, 'super_admin')->putToSuperAdmin("/tenants/{$tenant->id}", [
            'name' => $tenant->name,
            'slug' => $tenant->slug,
            'subdomain' => $tenant->subdomain,
            'is_active' => 1,
            'max_users' => '',
            'max_branches' => '',
        ])->assertRedirect();

        $tenant->refresh();
        $this->assertNull($tenant->max_users);
        $this->assertSame(5, $tenant->userLimit());
        $this->assertSame(1, $tenant->branchLimit());
    }

    public function test_super_admin_adding_a_tenant_user_beyond_the_limit_is_rejected(): void
    {
        $admin = PlatformAdmin::factory()->create();
        $tenant = Tenant::factory()->create(['max_users' => 1]);
        User::factory()->for($tenant)->create();
        $branch = Branch::factory()->for($tenant)->create();

        $response = $this->actingAs($admin, 'super_admin')->postToSuperAdmin("/tenants/{$tenant->id}/users", [
            'name' => 'Jane',
            'username' => 'jane',
            'password' => 'secret',
            'roles' => ['Owner'],
            'branch_ids' => [$branch->id],
        ]);

        $response->assertSessionHasErrors('limit');
    }
}
