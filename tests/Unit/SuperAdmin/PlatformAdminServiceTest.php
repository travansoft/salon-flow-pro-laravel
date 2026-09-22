<?php

namespace Tests\Unit\SuperAdmin;

use App\Models\PlatformAdmin;
use App\Repositories\Contracts\PlatformAdminRepositoryInterface;
use App\Services\SuperAdmin\PlatformAdminService;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Tests\TestCase;

class PlatformAdminServiceTest extends TestCase
{
    public function test_create_hashes_the_password_before_persisting(): void
    {
        $repository = Mockery::mock(PlatformAdminRepositoryInterface::class);
        $repository->shouldReceive('create')
            ->once()
            ->withArgs(fn (array $data) => $data['username'] === 'admin' && Hash::check('123', $data['password']))
            ->andReturn(new PlatformAdmin);

        $service = new PlatformAdminService($repository);

        $service->create(['name' => 'Admin', 'username' => 'admin', 'password' => '123']);
    }

    public function test_update_without_password_leaves_password_field_untouched(): void
    {
        $platformAdmin = new PlatformAdmin;

        $repository = Mockery::mock(PlatformAdminRepositoryInterface::class);
        $repository->shouldReceive('update')
            ->once()
            ->withArgs(fn (PlatformAdmin $p, array $data) => ! array_key_exists('password', $data))
            ->andReturn($platformAdmin);

        $service = new PlatformAdminService($repository);

        $service->update($platformAdmin, ['name' => 'Admin', 'username' => 'admin']);
    }

    public function test_update_with_password_hashes_the_new_password(): void
    {
        $platformAdmin = new PlatformAdmin;

        $repository = Mockery::mock(PlatformAdminRepositoryInterface::class);
        $repository->shouldReceive('update')
            ->once()
            ->withArgs(fn (PlatformAdmin $p, array $data) => Hash::check('newpass', $data['password']))
            ->andReturn($platformAdmin);

        $service = new PlatformAdminService($repository);

        $service->update($platformAdmin, ['name' => 'Admin', 'username' => 'admin', 'password' => 'newpass']);
    }
}
