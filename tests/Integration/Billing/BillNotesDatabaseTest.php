<?php

namespace Tests\Integration\Billing;

use App\Models\Bill;
use App\Models\Branch;
use App\Models\Tenant;
use App\Services\BranchContext;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillNotesDatabaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_note_column_persists_null_by_default(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);

        $bill = Bill::factory()->create(['tenant_id' => $tenant->id]);

        $this->assertDatabaseHas('bills', ['id' => $bill->id, 'notes' => null]);
    }

    public function test_note_persists_across_reload(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);

        $bill = Bill::factory()->create(['tenant_id' => $tenant->id, 'notes' => 'Paid in two installments.']);

        $this->assertDatabaseHas('bills', [
            'id' => $bill->id,
            'notes' => 'Paid in two installments.',
        ]);
        $this->assertSame('Paid in two installments.', Bill::find($bill->id)->notes);
    }
}
