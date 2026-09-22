<?php

namespace Tests\Integration\SuperAdmin;

use App\Models\Tenant;
use App\Services\SuperAdmin\TenantUserService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TenantUserServiceTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private TenantUserService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->tenant = Tenant::factory()->create();
        $this->service = app(TenantUserService::class);
    }

    public function test_create_persists_user_with_hashed_password_and_assigned_roles(): void
    {
        $user = $this->service->create($this->tenant, [
            'name' => 'Jane',
            'username' => 'jane',
            'password' => 'secret',
            'roles' => ['Owner'],
        ]);

        $this->assertDatabaseHas('users', ['id' => $user->id, 'tenant_id' => $this->tenant->id, 'username' => 'jane']);
        $this->assertTrue($user->fresh()->hasRole('Owner'));
    }

    public function test_disable_login_removes_the_users_active_sessions(): void
    {
        $user = $this->service->create($this->tenant, ['name' => 'Jane', 'username' => 'jane', 'password' => 'secret']);
        DB::table('sessions')->insert(['id' => 'sess-1', 'user_id' => $user->id, 'payload' => '', 'last_activity' => now()->timestamp]);

        $this->service->disableLogin($user);

        $this->assertFalse($user->fresh()->isLoginEnabled());
        $this->assertDatabaseMissing('sessions', ['id' => 'sess-1']);
    }

    public function test_enable_login_restores_access(): void
    {
        $user = $this->service->create($this->tenant, ['name' => 'Jane', 'username' => 'jane', 'password' => 'secret']);
        $this->service->disableLogin($user);

        $this->service->enableLogin($user);

        $this->assertTrue($user->fresh()->isLoginEnabled());
    }
}
