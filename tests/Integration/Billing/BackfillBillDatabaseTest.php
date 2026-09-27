<?php

namespace Tests\Integration\Billing;

use App\Models\Branch;
use App\Models\Client;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Repositories\Contracts\BillRepositoryInterface;
use App\Services\BillingService;
use App\Services\BranchContext;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BackfillBillDatabaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_backfilled_bill_line_item_persists_an_edited_rate_that_differs_from_the_service_price(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $user = User::factory()->for($tenant)->create();
        $service = Service::factory()->create(['tenant_id' => $tenant->id, 'price' => 500]);

        $bill = app(BillingService::class)->createManualBill($client->id, $user->id, [
            ['description' => $service->name, 'service_id' => $service->id, 'unit_price' => 350, 'tax_rate' => 18],
        ], 0, Carbon::parse('2026-01-10'));

        $this->assertDatabaseHas('bill_line_items', [
            'bill_id' => $bill->id,
            'service_id' => $service->id,
        ]);
        $this->assertNotSame('500.00', (string) $bill->lineItems->first()->unit_price);
    }

    public function test_backfilled_bill_and_its_payment_are_persisted_with_the_backdated_created_at(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $user = User::factory()->for($tenant)->create();

        $billDate = Carbon::parse('2025-11-20');

        $bill = app(BillingService::class)->createManualBill($client->id, $user->id, [
            ['description' => 'Haircut', 'unit_price' => 500],
        ], 0, $billDate);
        app(BillingService::class)->recordPayments($bill, [['method' => 'cash', 'amount' => (float) $bill->total]], $user->id);

        $this->assertDatabaseHas('bills', [
            'id' => $bill->id,
            'status' => 'paid',
        ]);
        $this->assertSame('2025-11-20', $bill->fresh()->created_at->toDateString());
    }

    public function test_backfilled_bill_is_included_in_reporting_queries_scoped_to_its_backdated_date(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $user = User::factory()->for($tenant)->create();

        $billDate = Carbon::parse('2026-02-05');

        app(BillingService::class)->createManualBill($client->id, $user->id, [
            ['description' => 'Haircut', 'unit_price' => 500],
        ], 0, $billDate);

        $repository = app(BillRepositoryInterface::class);
        $bills = $repository->forDateRange($billDate->copy()->startOfDay(), $billDate->copy()->endOfDay());

        $this->assertCount(1, $bills);
    }
}
