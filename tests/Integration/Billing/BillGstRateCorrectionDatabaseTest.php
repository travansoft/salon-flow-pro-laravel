<?php

namespace Tests\Integration\Billing;

use App\Models\BillAudit;
use App\Models\Tenant;
use App\Services\BillGstRateCorrectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreatesInclusiveBills;
use Tests\TestCase;

class BillGstRateCorrectionDatabaseTest extends TestCase
{
    use CreatesInclusiveBills, RefreshDatabase;

    public function test_correction_rewrites_line_rows_and_records_an_audit_entry(): void
    {
        $tenant = Tenant::factory()->create();
        $bill = $this->billFor($tenant, [['description' => 'Hair Color', 'unit_price' => 1000, 'tax_rate' => 18]]);

        app(BillGstRateCorrectionService::class)->correct('18', '5', Carbon::today(), Carbon::today(), true, null);

        $this->assertDatabaseHas('bill_line_items', [
            'bill_id' => $bill->id,
            'tax_rate' => 5,
            'unit_price' => 952.38,
            'line_total' => 952.38,
            'cgst_amount' => 23.81,
            'sgst_amount' => 23.81,
        ]);
        $this->assertDatabaseHas('bills', ['id' => $bill->id, 'subtotal' => 952.38, 'tax_amount' => 47.62, 'total' => 1000]);
        $this->assertDatabaseHas('bill_audits', [
            'bill_id' => $bill->id,
            'action' => BillAudit::ActionEdited,
            'field' => 'gst_rate',
            'old_value' => '18.00%',
            'new_value' => '5.00%',
        ]);
    }

    public function test_only_bills_inside_the_date_range_are_rewritten(): void
    {
        $tenant = Tenant::factory()->create();
        $old = $this->billFor($tenant, [['description' => 'Hair Color', 'unit_price' => 1000, 'tax_rate' => 18]], billDate: Carbon::today()->subDays(30));
        $recent = $this->billFor($tenant, [['description' => 'Hair Color', 'unit_price' => 1000, 'tax_rate' => 18]]);

        app(BillGstRateCorrectionService::class)->correct('18', '5', Carbon::today()->subDays(40), Carbon::today()->subDays(20), true);

        $this->assertDatabaseHas('bills', ['id' => $old->id, 'tax_amount' => 47.62]);
        $this->assertDatabaseHas('bills', ['id' => $recent->id, 'tax_amount' => 152.55]);
    }

    public function test_other_tenants_bills_are_never_touched(): void
    {
        $tenant = Tenant::factory()->create();
        $other = Tenant::factory()->create();
        $otherBill = $this->billFor($other, [['description' => 'Hair Color', 'unit_price' => 1000, 'tax_rate' => 18]]);
        $this->billFor($tenant, [['description' => 'Hair Color', 'unit_price' => 1000, 'tax_rate' => 18]]);

        app(BillGstRateCorrectionService::class)->correct('18', '5', Carbon::today(), Carbon::today(), true);

        $this->assertDatabaseHas('bills', ['id' => $otherBill->id, 'tax_amount' => 152.55]);
    }
}
