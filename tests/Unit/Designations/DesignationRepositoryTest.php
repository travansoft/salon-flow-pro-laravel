<?php

namespace Tests\Unit\Designations;

use App\Models\Branch;
use App\Models\Designation;
use App\Models\Tenant;
use App\Repositories\Eloquent\DesignationRepository;
use App\Services\BranchContext;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DesignationRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_active_excludes_disabled_designations(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);
        Designation::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Senior Stylist']);
        Designation::factory()->inactive()->create(['tenant_id' => $tenant->id, 'name' => 'Retired Role']);

        $repository = app(DesignationRepository::class);

        $names = $repository->getActive()->pluck('name');

        $this->assertTrue($names->contains('Senior Stylist'));
        $this->assertFalse($names->contains('Retired Role'));
    }

    public function test_get_all_returns_designations_ordered_by_name(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);
        Designation::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Stylist']);
        Designation::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Front Desk']);

        $repository = app(DesignationRepository::class);

        $names = $repository->getAll()->pluck('name')->all();

        $this->assertSame(['Front Desk', 'Stylist'], $names);
    }

    public function test_delete_soft_deletes_the_designation(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);
        $designation = Designation::factory()->create(['tenant_id' => $tenant->id]);

        $repository = app(DesignationRepository::class);
        $repository->delete($designation);

        $this->assertSoftDeleted('designations', ['id' => $designation->id]);
    }
}
