<?php

namespace Tests\Regression\Roles;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DirectorRoleMissingAfterMigrateFixTest extends TestCase
{
    use RefreshDatabase;

    public function test_director_role_exists_after_migrations_without_running_seeder(): void
    {
        $director = Role::where('name', 'Director')->whereNull('tenant_id')->first();

        $this->assertNotNull($director);
        $this->assertTrue($director->hasPermissionTo('dashboard.view'));
        $this->assertFalse($director->hasPermissionTo('billing.create'));
    }
}
