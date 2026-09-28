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

class DiscountReducesTheGstInclusiveTotalFixTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Bug: discount_percent and discount_amount were both applied against the
     * pre-tax (GST-exclusive) line amount, then GST was recalculated on the
     * discounted base. This meant the discount effectively also stripped GST
     * off itself, so a typed discount_amount of 84.74 on a 1000 GST-inclusive
     * item shrank the bill by ~100.01 instead of 84.74, and a 10% discount
     * gave a total that was off by a paisa from the customer-facing 10% off
     * shown in the UI preview. Fixed by applying the discount directly to the
     * GST-inclusive line amount and deriving tax as the remainder needed to
     * make taxable + tax equal (inclusive - discount) exactly.
     */
    public function test_discount_amount_reduces_the_inclusive_total_by_exactly_what_was_typed(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $user = User::factory()->for($tenant)->create();

        $bill = app(BillingService::class)->createManualBill($client->id, $user->id, [
            ['description' => 'Hair Color', 'unit_price' => 1000, 'tax_rate' => 18],
        ], 0, discountAmount: 84.74);

        $this->assertSame('84.74', (string) $bill->discount_amount);
        $this->assertSame('915.26', (string) $bill->total);
    }

    public function test_discount_percent_reduces_the_inclusive_total_by_exactly_that_percent(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $user = User::factory()->for($tenant)->create();

        $bill = app(BillingService::class)->createManualBill($client->id, $user->id, [
            ['description' => 'Hair Color', 'unit_price' => 1000, 'tax_rate' => 18],
        ], 10);

        $this->assertSame('100.00', (string) $bill->discount_amount);
        $this->assertSame('900.00', (string) $bill->total);
    }
}
