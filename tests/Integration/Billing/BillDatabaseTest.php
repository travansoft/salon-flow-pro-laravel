<?php

namespace Tests\Integration\Billing;

use App\Models\Bill;
use App\Models\Branch;
use App\Models\StaffProfile;
use App\Models\Tenant;
use App\Services\BranchContext;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillDatabaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_scope_excludes_bills_from_other_tenants(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        Bill::factory()->create(['tenant_id' => $tenantA->id]);
        Bill::factory()->create(['tenant_id' => $tenantB->id]);

        app(TenantContext::class)->set($tenantA);
        app(BranchContext::class)->set(Branch::defaultForTenant($tenantA->id));

        $this->assertSame(1, Bill::count());
    }

    public function test_voiding_a_bill_soft_deletes_neither_the_bill_nor_its_payments(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);

        $bill = Bill::factory()->create(['tenant_id' => $tenant->id]);
        $bill->payments()->create(['tenant_id' => $tenant->id, 'method' => 'cash', 'amount' => 500]);

        $bill->update(['status' => Bill::StatusVoid]);

        $this->assertDatabaseHas('bills', ['id' => $bill->id, 'status' => 'void']);
        $this->assertDatabaseHas('bill_payments', ['bill_id' => $bill->id]);
    }

    public function test_deleting_a_staff_profile_nulls_out_staff_profile_id_on_line_items(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);

        $staffProfile = StaffProfile::factory()->create(['tenant_id' => $tenant->id]);
        $bill = Bill::factory()->create(['tenant_id' => $tenant->id]);
        $lineItem = $bill->lineItems()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $bill->branch_id,
            'staff_profile_id' => $staffProfile->id,
            'description' => 'Haircut',
            'unit_price' => 500,
            'line_total' => 500,
        ]);

        $staffProfile->forceDelete();

        $this->assertDatabaseHas('bill_line_items', ['id' => $lineItem->id, 'staff_profile_id' => null]);
    }
}
