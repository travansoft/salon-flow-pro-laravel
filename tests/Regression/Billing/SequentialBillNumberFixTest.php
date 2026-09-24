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

class SequentialBillNumberFixTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Bug risk: computing the next bill number as "count of existing bills + 1"
     * (rather than max(bill_number) + 1) breaks as soon as any bill is ever
     * deleted or soft-deleted, producing a duplicate GST invoice number.
     * BillRepository::nextBillNumber() uses max(bill_number) under a
     * row lock specifically to avoid this and to stay correct under
     * concurrent bill creation.
     */
    public function test_bill_numbering_stays_sequential_even_after_a_bill_is_soft_deleted(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $user = User::factory()->for($tenant)->create();

        $first = app(BillingService::class)->createManualBill($client->id, $user->id, [
            ['description' => 'Item A', 'unit_price' => 100],
        ]);
        $first->delete();

        $second = app(BillingService::class)->createManualBill($client->id, $user->id, [
            ['description' => 'Item B', 'unit_price' => 100],
        ]);

        $this->assertGreaterThan($first->bill_number, $second->bill_number);
    }

    public function test_bill_numbers_do_not_collide_across_tenants(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        app(TenantContext::class)->set($tenantA);
        $branchA = Branch::factory()->create(['tenant_id' => $tenantA->id]);
        app(BranchContext::class)->set($branchA);
        $clientA = Client::factory()->create(['tenant_id' => $tenantA->id]);
        $userA = User::factory()->for($tenantA)->create();
        $billA = app(BillingService::class)->createManualBill($clientA->id, $userA->id, [
            ['description' => 'Item A', 'unit_price' => 100],
        ]);

        app(TenantContext::class)->set($tenantB);
        $branchB = Branch::factory()->create(['tenant_id' => $tenantB->id]);
        app(BranchContext::class)->set($branchB);
        $clientB = Client::factory()->create(['tenant_id' => $tenantB->id]);
        $userB = User::factory()->for($tenantB)->create();
        $billB = app(BillingService::class)->createManualBill($clientB->id, $userB->id, [
            ['description' => 'Item B', 'unit_price' => 100],
        ]);

        $this->assertSame(1, $billA->bill_number);
        $this->assertSame(1, $billB->bill_number);
    }
}
