<?php

namespace Tests\Regression\Services;

use App\Models\Branch;
use App\Models\Service;
use App\Models\Tenant;
use App\Services\BranchContext;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SoftDeletedServiceCodeCanBeReusedFixTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Bug: the (tenant_id, code) unique index/constraint covered every row
     * including soft-deleted ones, so once a service was disabled/deleted,
     * its POS code was permanently unusable — creating or editing another
     * service with that same code failed with a DB unique-constraint error,
     * and QuickBillService::findServiceByCode() would also never find a
     * legitimate replacement service reusing that code. Fixed by replacing
     * the index with a partial unique index (Postgres) that only applies to
     * deleted_at IS NULL rows, and scoping the validation Rule::unique the
     * same way.
     */
    public function test_a_new_service_can_reuse_the_code_of_a_soft_deleted_service(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);

        $original = Service::factory()->create(['tenant_id' => $tenant->id, 'code' => '205']);
        $original->delete();

        $replacement = Service::factory()->create(['tenant_id' => $tenant->id, 'code' => '205']);

        $this->assertSame('205', $replacement->code);
        $this->assertTrue(Service::withTrashed()->find($original->id)->trashed());
    }
}
