<?php

namespace Tests\Unit\Branches;

use App\Models\Branch;
use App\Models\Tenant;
use App\Repositories\Contracts\BranchRepositoryInterface;
use App\Services\BranchService;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class BranchServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_persists_branch_under_the_current_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $tenantContext = app(TenantContext::class);
        $tenantContext->set($tenant);

        $repository = Mockery::mock(BranchRepositoryInterface::class);
        $repository->shouldReceive('create')
            ->once()
            ->andReturnUsing(fn (array $data) => Branch::create($data));

        $service = new BranchService($repository, $tenantContext);

        $created = $service->create([
            'name' => 'Marine Drive',
            'slug' => 'marine-drive',
            'invoice_prefix' => 'MDV',
        ]);

        $this->assertSame('Marine Drive', $created->name);
        $this->assertSame($tenant->id, $created->tenant_id);
        $this->assertTrue($created->is_active);
    }

    public function test_update_persists_changed_fields(): void
    {
        $tenant = Tenant::factory()->create();
        $tenantContext = app(TenantContext::class);
        $tenantContext->set($tenant);

        $existingBranch = Branch::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Old Name']);

        $repository = Mockery::mock(BranchRepositoryInterface::class);
        $repository->shouldReceive('update')
            ->once()
            ->andReturnUsing(function ($branch, array $data) {
                $branch->update($data);

                return $branch;
            });

        $service = new BranchService($repository, $tenantContext);
        $service->update($existingBranch, ['name' => 'New Name']);

        $this->assertSame('New Name', $existingBranch->fresh()->name);
    }

    public function test_deactivate_marks_branch_inactive(): void
    {
        $tenant = Tenant::factory()->create();
        $tenantContext = app(TenantContext::class);
        $tenantContext->set($tenant);

        $existingBranch = Branch::factory()->create(['tenant_id' => $tenant->id]);

        $repository = Mockery::mock(BranchRepositoryInterface::class);
        $repository->shouldReceive('update')
            ->once()
            ->andReturnUsing(function ($branch, array $data) {
                $branch->update($data);

                return $branch;
            });

        $service = new BranchService($repository, $tenantContext);
        $service->deactivate($existingBranch);

        $this->assertFalse($existingBranch->fresh()->is_active);
    }
}
