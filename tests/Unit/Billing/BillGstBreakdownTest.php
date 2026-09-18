<?php

namespace Tests\Unit\Billing;

use App\Models\Client;
use App\Models\Tenant;
use App\Models\User;
use App\Services\BillingService;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillGstBreakdownTest extends TestCase
{
    use RefreshDatabase;

    public function test_single_rate_bill_has_one_breakdown_entry(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $user = User::factory()->for($tenant)->create();

        $bill = app(BillingService::class)->createManualBill($client->id, $user->id, [
            ['description' => 'Haircut', 'unit_price' => 500, 'tax_rate' => 18],
        ]);

        $breakdown = $bill->gstBreakdownByRate();

        $this->assertCount(1, $breakdown);
        $this->assertArrayHasKey('18.00', $breakdown);
        $this->assertSame('500.00', $breakdown['18.00']['taxable']);
    }

    public function test_mixed_rate_bill_groups_each_rate_separately(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $user = User::factory()->for($tenant)->create();

        $bill = app(BillingService::class)->createManualBill($client->id, $user->id, [
            ['description' => 'Hair Color', 'unit_price' => 100, 'tax_rate' => 5],
            ['description' => 'Spa Package', 'unit_price' => 200, 'tax_rate' => 18],
        ]);

        $breakdown = $bill->gstBreakdownByRate();

        $this->assertCount(2, $breakdown);
        $this->assertSame('100.00', $breakdown['5.00']['taxable']);
        $this->assertSame('200.00', $breakdown['18.00']['taxable']);
    }

    public function test_breakdown_omits_rates_with_no_amount(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $user = User::factory()->for($tenant)->create();

        $bill = app(BillingService::class)->createManualBill($client->id, $user->id, [
            ['description' => 'Haircut', 'unit_price' => 500, 'tax_rate' => 18],
        ]);

        $breakdown = collect($bill->gstBreakdownByRate())->filter(fn ($amounts) => bccomp($amounts['taxable'], '0', 2) > 0);

        $this->assertCount(1, $breakdown);
    }
}
