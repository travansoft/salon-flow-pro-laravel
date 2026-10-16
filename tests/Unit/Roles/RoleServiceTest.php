<?php

namespace Tests\Unit\Roles;

use App\Models\Role;
use App\Models\Tenant;
use App\Repositories\Contracts\RoleRepositoryInterface;
use App\Services\RoleService;
use InvalidArgumentException;
use Mockery;
use Tests\TestCase;

class RoleServiceTest extends TestCase
{
    public function test_create_scopes_role_to_the_given_tenant(): void
    {
        $tenant = new Tenant;
        $tenant->id = 7;
        $repository = Mockery::mock(RoleRepositoryInterface::class);
        $repository->shouldReceive('create')
            ->once()
            ->with(['tenant_id' => 7, 'name' => 'Receptionist', 'description' => null], ['clients.view'])
            ->andReturn(new Role);

        $service = new RoleService($repository);

        $this->assertInstanceOf(Role::class, $service->create($tenant, [
            'name' => 'Receptionist',
            'permissions' => ['clients.view'],
        ]));
    }

    public function test_create_without_tenant_makes_a_system_role(): void
    {
        $repository = Mockery::mock(RoleRepositoryInterface::class);
        $repository->shouldReceive('create')
            ->once()
            ->with(Mockery::on(fn (array $data): bool => $data['tenant_id'] === null), [])
            ->andReturn(new Role);

        (new RoleService($repository))->create(null, ['name' => 'Auditor']);
    }

    public function test_update_keeps_the_name_of_a_built_in_role(): void
    {
        $role = new Role(['name' => 'Owner']);
        $repository = Mockery::mock(RoleRepositoryInterface::class);
        $repository->shouldReceive('update')
            ->once()
            ->with($role, ['description' => 'Updated'], ['staff.view'])
            ->andReturn($role);

        (new RoleService($repository))->update($role, [
            'name' => 'Renamed',
            'description' => 'Updated',
            'permissions' => ['staff.view'],
        ]);
    }

    public function test_delete_rejects_a_built_in_role(): void
    {
        $repository = Mockery::mock(RoleRepositoryInterface::class);
        $repository->shouldReceive('delete')->never();

        $this->expectException(InvalidArgumentException::class);

        (new RoleService($repository))->delete(new Role(['name' => 'Manager']));
    }

    public function test_delete_rejects_a_role_that_is_assigned_to_users(): void
    {
        $role = new Role(['name' => 'Receptionist', 'tenant_id' => 3]);
        $repository = Mockery::mock(RoleRepositoryInterface::class);
        $repository->shouldReceive('countUsers')->once()->with($role)->andReturn(2);
        $repository->shouldReceive('delete')->never();

        $this->expectException(InvalidArgumentException::class);

        (new RoleService($repository))->delete($role);
    }

    public function test_delete_removes_an_unused_custom_role(): void
    {
        $role = new Role(['name' => 'Receptionist', 'tenant_id' => 3]);
        $repository = Mockery::mock(RoleRepositoryInterface::class);
        $repository->shouldReceive('countUsers')->once()->andReturn(0);
        $repository->shouldReceive('delete')->once()->with($role)->andReturn(true);

        (new RoleService($repository))->delete($role);
    }
}
