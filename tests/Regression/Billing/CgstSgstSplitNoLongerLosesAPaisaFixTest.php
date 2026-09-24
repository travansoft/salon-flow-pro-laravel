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

class CgstSgstSplitNoLongerLosesAPaisaFixTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Bug: splitTax() rounded CGST and SGST independently via bcdiv($tax, '2', 2),
     * which truncates rather than rounds. For an odd-paisa tax amount (e.g. 123.55),
     * this produced 61.77 + 61.77 = 123.54, a paisa short of the actual tax charged.
     * The bill's stored total (derived from the combined tax_amount) stayed correct,
     * but a customer adding up the printed CGST + SGST lines would land a paisa
     * short of the total payable. Fixed by rounding CGST once and deriving SGST as
     * the exact remainder, guaranteeing cgst_amount + sgst_amount == tax_amount.
     */
    public function test_cgst_and_sgst_always_sum_to_the_exact_tax_amount(): void
    {
        $tenant = Tenant::factory()->create(['gst_state_code' => '32']);
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);
        $client = Client::factory()->create(['tenant_id' => $tenant->id, 'gst_number' => '32AAAAA0000A1Z5']);
        $user = User::factory()->for($tenant)->create();

        $bill = app(BillingService::class)->createManualBill($client->id, $user->id, [
            ['description' => 'Gents Hair Cut', 'unit_price' => 250, 'tax_rate' => 18],
            ['description' => 'Ladies Haircut', 'unit_price' => 350, 'tax_rate' => 18],
            ['description' => 'Facial', 'unit_price' => 1200, 'tax_rate' => 18],
        ], 10);

        $summedCgstAndSgst = bcadd((string) $bill->cgst_amount, (string) $bill->sgst_amount, 2);

        $this->assertSame((string) $bill->tax_amount, $summedCgstAndSgst);

        $summedTotal = bcadd(bcsub((string) $bill->subtotal, (string) $bill->discount_amount, 2), $summedCgstAndSgst, 2);
        $this->assertSame((string) $bill->total, $summedTotal);
    }
}
