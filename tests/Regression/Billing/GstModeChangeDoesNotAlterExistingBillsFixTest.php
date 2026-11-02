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

class GstModeChangeDoesNotAlterExistingBillsFixTest extends TestCase
{
    use RefreshDatabase;

    public function test_switching_to_additional_gst_leaves_a_previously_issued_bill_unchanged(): void
    {
        $tenant = Tenant::factory()->create(['gst_inclusive' => true]);
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $user = User::factory()->for($tenant)->create();

        $bill = app(BillingService::class)->createManualBill($client->id, $user->id, [
            ['description' => 'Hair Color', 'unit_price' => 1000, 'tax_rate' => 18],
        ]);

        $tenant->update(['gst_inclusive' => false]);

        $bill->refresh();
        $this->assertSame('1000.00', (string) $bill->total);
        $this->assertSame('847.45', (string) $bill->subtotal);
        $this->assertTrue($bill->prices_include_gst);
    }

    public function test_tenant_without_an_explicit_mode_defaults_to_inclusive_billing(): void
    {
        $tenant = new Tenant;

        $this->assertTrue($tenant->gst_inclusive);
    }
}
