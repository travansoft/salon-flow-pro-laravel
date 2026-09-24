<?php

namespace Tests\Regression\Billing;

use App\Models\Branch;
use App\Models\Client;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Services\BillingService;
use App\Services\BranchContext;
use App\Services\QuickBillService;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InclusivePriceNoLongerLosesAPaisaFixTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Bug: Service::price was stored GST-exclusive, derived from the admin's
     * GST-inclusive input by dividing and rounding to 2dp at save time
     * (e.g. ₹250 at 18% GST -> ₹211.86 stored). Billing then re-multiplied
     * that already-rounded exclusive price by the tax rate, which lost a
     * paisa to rounding twice: a ₹250 service billed as ₹249.99. Fixed by
     * storing price as the inclusive amount directly and deriving the
     * exclusive/tax split once, at billing time, from the inclusive line
     * total rather than from a pre-rounded per-unit exclusive price.
     */
    public function test_a_250_rupee_service_bills_at_exactly_250_rupees(): void
    {
        $tenant = Tenant::factory()->create(['default_gst_rate' => 18]);
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $user = User::factory()->for($tenant)->create();
        $service = Service::factory()->create(['tenant_id' => $tenant->id, 'price' => 250, 'tax_rate' => null]);

        $bill = app(BillingService::class)->createManualBill($client->id, $user->id, [
            ['service_id' => $service->id, 'description' => $service->name, 'unit_price' => (float) $service->price, 'tax_rate' => 18],
        ]);

        $this->assertSame('250.00', (string) $bill->total);
    }

    public function test_quick_bill_settle_also_bills_at_exactly_the_services_inclusive_price(): void
    {
        $tenant = Tenant::factory()->create(['default_gst_rate' => 18]);
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $user = User::factory()->for($tenant)->create();
        $service = Service::factory()->create(['tenant_id' => $tenant->id, 'price' => 250, 'tax_rate' => null]);

        $bill = app(QuickBillService::class)->createAndSettle(
            [['service_id' => $service->id, 'quantity' => 1]],
            ['client_id' => $client->id],
            'cash',
            $user->id,
        );

        $this->assertSame('250.00', (string) $bill->total);
    }
}
