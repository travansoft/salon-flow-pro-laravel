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

class AdditionalGstDatabaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_additional_gst_bill_persists_exclusive_lines_tax_and_mode_snapshot(): void
    {
        $tenant = Tenant::factory()->create(['gst_inclusive' => false]);
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $user = User::factory()->for($tenant)->create();

        $bill = app(BillingService::class)->createManualBill($client->id, $user->id, [
            ['description' => 'Hair Color', 'unit_price' => 1000, 'tax_rate' => 18],
        ]);

        $this->assertDatabaseHas('bills', [
            'id' => $bill->id,
            'subtotal' => 1000,
            'tax_amount' => 180,
            'total' => 1180,
            'prices_include_gst' => false,
        ]);
        $this->assertDatabaseHas('bill_line_items', [
            'bill_id' => $bill->id,
            'unit_price' => 1000,
            'line_total' => 1000,
            'cgst_amount' => 90,
            'sgst_amount' => 90,
        ]);
    }

    public function test_changing_the_gst_mode_applies_to_the_next_bill_immediately(): void
    {
        $tenant = Tenant::factory()->create(['gst_inclusive' => true]);
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $user = User::factory()->for($tenant)->create();
        $lines = [['description' => 'Hair Color', 'unit_price' => 1000, 'tax_rate' => 18]];

        $before = app(BillingService::class)->createManualBill($client->id, $user->id, $lines);
        $tenant->update(['gst_inclusive' => false]);
        $after = app(BillingService::class)->createManualBill($client->id, $user->id, $lines);

        $this->assertDatabaseHas('bills', ['id' => $before->id, 'total' => 1000, 'prices_include_gst' => true]);
        $this->assertDatabaseHas('bills', ['id' => $after->id, 'total' => 1180, 'prices_include_gst' => false]);
    }
}
