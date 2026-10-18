<?php

namespace Tests\Unit\Staff;

use App\Models\Designation;
use App\Models\StaffProfile;
use App\Models\Tenant;
use App\Repositories\Contracts\StaffProfileRepositoryInterface;
use App\Services\RoleService;
use App\Services\StaffService;
use App\Services\TenantContext;
use App\Services\TenantLimits;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class StaffServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    public function test_create_persists_user_staff_profile_and_roles_when_login_requested(): void
    {
        $tenant = Tenant::factory()->create();

        $tenantContext = app(TenantContext::class);
        $tenantContext->set($tenant);

        $designation = Designation::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Senior Stylist']);

        $repository = Mockery::mock(StaffProfileRepositoryInterface::class);
        $repository->shouldReceive('create')
            ->once()
            ->andReturnUsing(fn (array $data) => StaffProfile::create($data));

        $service = new StaffService($repository, $tenantContext, app(RoleService::class), app(TenantLimits::class));

        $staffProfile = $service->create([
            'name' => 'Priya Nair',
            'designation_id' => $designation->id,
            'create_login' => true,
            'username' => 'priya',
            'email' => 'priya@example.com',
            'password' => 'password123',
            'roles' => ['Stylist'],
        ]);

        $this->assertSame('Priya Nair', $staffProfile->name);
        $this->assertSame($designation->id, $staffProfile->designation_id);
        $this->assertSame($tenant->id, $staffProfile->user->tenant_id);
        $this->assertTrue($staffProfile->user->hasRole('Stylist'));
    }

    public function test_create_persists_staff_profile_without_a_login(): void
    {
        $tenant = Tenant::factory()->create();

        $tenantContext = app(TenantContext::class);
        $tenantContext->set($tenant);

        $repository = Mockery::mock(StaffProfileRepositoryInterface::class);
        $repository->shouldReceive('create')
            ->once()
            ->andReturnUsing(fn (array $data) => StaffProfile::create($data));

        $service = new StaffService($repository, $tenantContext, app(RoleService::class), app(TenantLimits::class));

        $staffProfile = $service->create([
            'name' => 'Ravi Kumar',
        ]);

        $this->assertSame('Ravi Kumar', $staffProfile->name);
        $this->assertNull($staffProfile->user_id);
        $this->assertFalse($staffProfile->hasLogin());
    }

    public function test_update_syncs_services_when_provided(): void
    {
        $tenant = Tenant::factory()->create();

        $tenantContext = app(TenantContext::class);
        $tenantContext->set($tenant);

        $staffProfile = StaffProfile::factory()->create(['tenant_id' => $tenant->id]);

        $repository = Mockery::mock(StaffProfileRepositoryInterface::class);
        $repository->shouldReceive('update')
            ->once()
            ->andReturnUsing(function ($profile, array $data) {
                $profile->update($data);

                return $profile;
            });

        $service = new StaffService($repository, $tenantContext, app(RoleService::class), app(TenantLimits::class));

        $service->update($staffProfile, ['name' => 'Updated Name', 'service_ids' => []]);

        $this->assertSame('Updated Name', $staffProfile->fresh()->name);
    }
}
