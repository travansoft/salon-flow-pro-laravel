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

class DiscountAmountNeverDriftsFromWhatWasTypedFixTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Bug (caught before shipping): an earlier version of the discount-amount
     * feature converted the typed amount into an equivalent discount_percent
     * first, then reapplied that percent through the normal per-line discount
     * math — the same path a typed percent goes through. Because bcmath
     * truncates rather than rounds, and discount_percent only stores 2 decimal
     * places, that round trip lost a paisa: typing an exact ₹84.74 discount on
     * a single ₹1000/18% line came back as ₹84.66 after reapplying the derived
     * percent. Fixed by allocating a typed discount_amount directly across
     * line items by each line's share of the subtotal (last line absorbing the
     * rounding remainder), so the stored discount_amount always equals exactly
     * what was typed — discount_percent is then derived only for display,
     * never fed back into the math.
     */
    public function test_a_typed_discount_amount_is_stored_exactly_even_though_its_equivalent_percent_would_round_differently(): void
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
        $this->assertSame('84.74', (string) $bill->lineItems->first()->discount_amount);
    }
}
