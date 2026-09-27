<?php

namespace Tests\Integration\Billing;

use App\Models\Branch;
use App\Models\Client;
use App\Models\Tenant;
use App\Models\User;
use App\Services\BillingService;
use App\Services\BranchContext;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillGstSplitDatabaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_same_state_client_persists_cgst_and_sgst(): void
    {
        $tenant = Tenant::factory()->create(['gst_state_code' => '32']);
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);
        $client = Client::factory()->create(['tenant_id' => $tenant->id, 'gst_number' => '32AAAAA0000A1Z5']);
        $user = User::factory()->for($tenant)->create();

        $bill = app(BillingService::class)->createManualBill($client->id, $user->id, [
            ['description' => 'Facial', 'unit_price' => 1000, 'tax_rate' => 18],
        ]);

        $this->assertDatabaseHas('bills', [
            'id' => $bill->id,
            'cgst_amount' => 76.27,
            'sgst_amount' => 76.28,
            'igst_amount' => 0,
        ]);
        $this->assertDatabaseHas('bill_line_items', [
            'bill_id' => $bill->id,
            'cgst_amount' => 76.27,
            'sgst_amount' => 76.28,
        ]);
    }

    public function test_different_state_client_persists_igst(): void
    {
        $tenant = Tenant::factory()->create(['gst_state_code' => '32']);
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);
        $client = Client::factory()->create(['tenant_id' => $tenant->id, 'gst_number' => '27AAAAA0000A1Z5']);
        $user = User::factory()->for($tenant)->create();

        $bill = app(BillingService::class)->createManualBill($client->id, $user->id, [
            ['description' => 'Facial', 'unit_price' => 1000, 'tax_rate' => 18],
        ]);

        $this->assertDatabaseHas('bills', [
            'id' => $bill->id,
            'cgst_amount' => 0,
            'sgst_amount' => 0,
            'igst_amount' => 152.55,
        ]);
    }
}
