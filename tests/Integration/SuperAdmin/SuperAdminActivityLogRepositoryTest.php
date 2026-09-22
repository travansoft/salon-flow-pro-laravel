<?php

namespace Tests\Integration\SuperAdmin;

use App\Models\PlatformAdmin;
use App\Models\SuperAdminActivityLog;
use App\Repositories\Eloquent\SuperAdminActivityLogRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperAdminActivityLogRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_paginate_latest_orders_by_most_recent_first(): void
    {
        $admin = PlatformAdmin::factory()->create();
        $older = SuperAdminActivityLog::factory()->for($admin, 'platformAdmin')->create(['created_at' => now()->subDay()]);
        $newer = SuperAdminActivityLog::factory()->for($admin, 'platformAdmin')->create(['created_at' => now()]);

        $repository = new SuperAdminActivityLogRepository(new SuperAdminActivityLog);
        $page = $repository->paginateLatest();

        $this->assertSame($newer->id, $page->first()->id);
        $this->assertSame($older->id, $page->last()->id);
    }

    public function test_create_persists_a_log_entry(): void
    {
        $repository = new SuperAdminActivityLogRepository(new SuperAdminActivityLog);

        $log = $repository->create([
            'platform_admin_id' => null,
            'platform_admin_name' => 'System',
            'action' => 'tenant.created',
            'description' => 'Created tenant "Studio"',
        ]);

        $this->assertDatabaseHas('super_admin_activity_logs', ['id' => $log->id]);
    }
}
