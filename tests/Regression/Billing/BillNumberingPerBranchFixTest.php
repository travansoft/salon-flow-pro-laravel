<?php

namespace Tests\Regression\Billing;

use App\Models\Branch;
use App\Models\Client;
use App\Models\Tenant;
use App\Models\User;
use App\Services\BillingService;
use App\Services\BranchContext;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillNumberingPerBranchFixTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Bug risk: invoice numbering must be sequential per branch, not shared
     * across a tenant's branches, and the invoice number's prefix must come
     * from the branch that issued it, not a hardcoded "INV".
     */
    public function test_each_branch_has_its_own_bill_number_sequence_and_prefix(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);

        $branchA = Branch::factory()->create(['tenant_id' => $tenant->id, 'invoice_prefix' => 'HSR']);
        $branchB = Branch::factory()->create(['tenant_id' => $tenant->id, 'invoice_prefix' => 'MDV']);

        app(BranchContext::class)->set($branchA);
        $clientA = Client::factory()->create(['tenant_id' => $tenant->id]);
        $userA = User::factory()->for($tenant)->create();
        $userA->branches()->sync([$branchA->id]);
        $firstBillBranchA = app(BillingService::class)->createManualBill($clientA->id, $userA->id, [
            ['description' => 'Haircut', 'unit_price' => 100],
        ]);
        $secondBillBranchA = app(BillingService::class)->createManualBill($clientA->id, $userA->id, [
            ['description' => 'Haircut', 'unit_price' => 100],
        ]);

        app(BranchContext::class)->set($branchB);
        $clientB = Client::factory()->create(['tenant_id' => $tenant->id]);
        $userB = User::factory()->for($tenant)->create();
        $userB->branches()->sync([$branchB->id]);
        $firstBillBranchB = app(BillingService::class)->createManualBill($clientB->id, $userB->id, [
            ['description' => 'Haircut', 'unit_price' => 100],
        ]);

        $this->assertSame(1, $firstBillBranchA->bill_number);
        $this->assertSame(2, $secondBillBranchA->bill_number);
        $this->assertSame(1, $firstBillBranchB->bill_number);

        $this->assertStringStartsWith('HSR/', $firstBillBranchA->invoiceNumber());
        $this->assertStringStartsWith('MDV/', $firstBillBranchB->invoiceNumber());
    }
}
