<?php

namespace Tests\Unit\Profile;

use App\Models\User;
use App\Repositories\Contracts\TenantUserRepositoryInterface;
use App\Services\ProfileService;
use Mockery;
use PHPUnit\Framework\TestCase;

class ProfileServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_change_password_delegates_to_repository(): void
    {
        $user = new User;
        $repository = Mockery::mock(TenantUserRepositoryInterface::class);
        $repository->shouldReceive('update')
            ->once()
            ->with($user, ['password' => 'new-secret'])
            ->andReturn($user);

        $service = new ProfileService($repository);
        $result = $service->changePassword($user, 'new-secret');

        $this->assertSame($user, $result);
    }
}
