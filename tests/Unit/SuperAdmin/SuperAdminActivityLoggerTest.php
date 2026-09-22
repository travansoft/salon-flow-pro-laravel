<?php

namespace Tests\Unit\SuperAdmin;

use App\Models\PlatformAdmin;
use App\Models\SuperAdminActivityLog;
use App\Repositories\Contracts\SuperAdminActivityLogRepositoryInterface;
use App\Services\SuperAdmin\SuperAdminActivityLogger;
use Illuminate\Support\Facades\Auth;
use Mockery;
use Tests\TestCase;

class SuperAdminActivityLoggerTest extends TestCase
{
    public function test_log_attributes_the_action_to_the_authenticated_super_admin(): void
    {
        $admin = new PlatformAdmin(['name' => 'Jane Admin']);
        $admin->id = 7;

        Auth::shouldReceive('guard')->with('super_admin')->andReturnSelf();
        Auth::shouldReceive('user')->andReturn($admin);

        $repository = Mockery::mock(SuperAdminActivityLogRepositoryInterface::class);
        $repository->shouldReceive('create')
            ->once()
            ->withArgs(fn (array $data) => $data['platform_admin_id'] === 7 && $data['platform_admin_name'] === 'Jane Admin' && $data['action'] === 'tenant.created')
            ->andReturn(new SuperAdminActivityLog);

        $logger = new SuperAdminActivityLogger($repository);

        $logger->log('tenant.created', 'Created tenant "Studio"');
    }

    public function test_log_as_uses_the_explicitly_given_actor_identity(): void
    {
        $repository = Mockery::mock(SuperAdminActivityLogRepositoryInterface::class);
        $repository->shouldReceive('create')
            ->once()
            ->withArgs(fn (array $data) => $data['platform_admin_id'] === 42 && $data['platform_admin_name'] === 'Impersonator')
            ->andReturn(new SuperAdminActivityLog);

        $logger = new SuperAdminActivityLogger($repository);

        $logger->logAs(42, 'Impersonator', 'tenant_user.impersonation_ended', 'Returned to the platform panel.');
    }
}
