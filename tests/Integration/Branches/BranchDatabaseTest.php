<?php

namespace Tests\Integration\Branches;

use App\Models\Branch;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchDatabaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_scope_excludes_branches_from_other_tenants(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        Branch::factory()->create(['tenant_id' => $tenantA->id]);
        Branch::factory()->create(['tenant_id' => $tenantB->id]);

        app(TenantContext::class)->set($tenantA);

        $this->assertSame(1, Branch::count());
    }

    public function test_slug_is_unique_per_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        Branch::factory()->create(['tenant_id' => $tenant->id, 'slug' => 'main']);

        $this->expectException(QueryException::class);

        Branch::factory()->create(['tenant_id' => $tenant->id, 'slug' => 'main']);
    }

    public function test_invoice_prefix_is_unique_per_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        Branch::factory()->create(['tenant_id' => $tenant->id, 'invoice_prefix' => 'MDV']);

        $this->expectException(QueryException::class);

        Branch::factory()->create(['tenant_id' => $tenant->id, 'invoice_prefix' => 'MDV']);
    }

    public function test_the_same_slug_can_be_reused_across_different_tenants(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        Branch::factory()->create(['tenant_id' => $tenantA->id, 'slug' => 'main']);
        $branchB = Branch::factory()->create(['tenant_id' => $tenantB->id, 'slug' => 'main']);

        $this->assertDatabaseHas('branches', ['id' => $branchB->id, 'slug' => 'main']);
    }

    public function test_user_branch_pivot_maps_a_user_to_multiple_branches(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);

        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $branchA = Branch::factory()->create(['tenant_id' => $tenant->id]);
        $branchB = Branch::factory()->create(['tenant_id' => $tenant->id]);

        $user->branches()->sync([$branchA->id, $branchB->id]);

        $this->assertCount(2, $user->fresh()->branches);
        $this->assertTrue($branchA->fresh()->users->contains($user));
    }

    public function test_deleting_a_branch_cascades_to_the_user_branch_pivot(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);

        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        $user->branches()->sync([$branch->id]);

        $branch->forceDelete();

        $this->assertDatabaseMissing('user_branch', ['branch_id' => $branch->id]);
    }
}
