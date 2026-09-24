<?php

namespace Tests\Unit\Billing;

use App\Models\Branch;
use App\Models\Client;
use App\Models\Tenant;
use App\Models\User;
use App\Services\BillingService;
use App\Services\BranchContext;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BackfillBillTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_manual_bill_with_a_bill_date_sets_created_at_to_that_date(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $user = User::factory()->for($tenant)->create();

        $billDate = Carbon::parse('2026-01-15');

        $bill = app(BillingService::class)->createManualBill($client->id, $user->id, [
            ['description' => 'Haircut', 'unit_price' => 500],
        ], 0, $billDate);

        $this->assertTrue($bill->created_at->isSameDay($billDate));
    }

    public function test_create_manual_bill_without_a_bill_date_defaults_to_now(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $user = User::factory()->for($tenant)->create();

        $bill = app(BillingService::class)->createManualBill($client->id, $user->id, [
            ['description' => 'Haircut', 'unit_price' => 500],
        ]);

        $this->assertTrue($bill->created_at->isToday());
    }

    public function test_backdated_bill_is_numbered_within_the_financial_year_of_its_bill_date(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $user = User::factory()->for($tenant)->create();

        $bill = app(BillingService::class)->createManualBill($client->id, $user->id, [
            ['description' => 'Haircut', 'unit_price' => 500],
        ], 0, Carbon::parse('2024-05-10'));

        $this->assertSame('2024-25', $bill->financial_year);
    }
}
