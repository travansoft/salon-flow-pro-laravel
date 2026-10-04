<?php

namespace Tests\Integration\Billing;

use App\Models\BillDraft;
use App\Models\Branch;
use App\Models\Tenant;
use App\Models\User;
use App\Repositories\Contracts\BillDraftRepositoryInterface;
use App\Services\BranchContext;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillDraftDatabaseTest extends TestCase
{
    use RefreshDatabase;

    private function actAsTenant(Tenant $tenant): void
    {
        app(TenantContext::class)->set($tenant);
        app(BranchContext::class)->set(Branch::defaultForTenant($tenant->id));
    }

    public function test_tenant_scope_excludes_drafts_from_other_tenants(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        BillDraft::factory()->create(['tenant_id' => $tenantA->id]);
        BillDraft::factory()->create(['tenant_id' => $tenantB->id]);

        $this->actAsTenant($tenantA);

        $this->assertSame(1, BillDraft::count());
    }

    public function test_payload_round_trips_as_an_array(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actAsTenant($tenant);

        $draft = BillDraft::factory()->create([
            'tenant_id' => $tenant->id,
            'payload' => ['lines' => [['description' => 'Haircut']], 'notes' => 'x'],
        ]);

        $this->assertSame('Haircut', $draft->fresh()->payload['lines'][0]['description']);
    }

    public function test_repository_returns_only_the_requested_users_drafts_newest_first(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actAsTenant($tenant);
        $owner = User::factory()->create(['tenant_id' => $tenant->id]);
        $other = User::factory()->create(['tenant_id' => $tenant->id]);

        $older = BillDraft::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $owner->id, 'updated_at' => now()->subHour()]);
        $newer = BillDraft::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $owner->id]);
        BillDraft::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $other->id]);

        $drafts = app(BillDraftRepositoryInterface::class)->forUser($owner->id);

        $this->assertSame([$newer->id, $older->id], $drafts->pluck('id')->all());
    }

    public function test_deleting_a_user_removes_their_drafts(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $draft = BillDraft::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $user->id]);

        $user->forceDelete();

        $this->assertDatabaseMissing('bill_drafts', ['id' => $draft->id]);
    }
}
