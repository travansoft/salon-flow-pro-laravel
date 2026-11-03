<?php

namespace Tests\Regression\Billing;

use App\Models\Tenant;
use App\Services\BillGstRateCorrectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreatesInclusiveBills;
use Tests\TestCase;

class GstCorrectionNeverChangesWhatCustomerPaidFixTest extends TestCase
{
    use CreatesInclusiveBills, RefreshDatabase;

    /**
     * Bug: a tenant billed at 18% by mistake when the rate was 5%. Correcting
     * the old bills must only move GST inside the price; the total each
     * customer was charged has to stay exactly as it was.
     */
    public function test_discounted_bill_keeps_its_total_after_correction(): void
    {
        $tenant = Tenant::factory()->create();
        $bill = $this->billFor($tenant, [['description' => 'Hair Color', 'unit_price' => 1000, 'tax_rate' => 18]], discountPercent: 10);

        app(BillGstRateCorrectionService::class)->correct('18', '5', Carbon::today(), Carbon::today(), true);

        $this->assertSame('900.00', (string) $bill->refresh()->total);
    }

    public function test_mixed_rate_bill_only_changes_the_lines_at_the_old_rate(): void
    {
        $tenant = Tenant::factory()->create();
        $bill = $this->billFor($tenant, [
            ['description' => 'Hair Color', 'unit_price' => 1000, 'tax_rate' => 18],
            ['description' => 'Threading', 'unit_price' => 100, 'tax_rate' => 12],
        ]);

        app(BillGstRateCorrectionService::class)->correct('18', '5', Carbon::today(), Carbon::today(), true);

        $this->assertSame('1100.00', (string) $bill->refresh()->total);
        $this->assertDatabaseHas('bill_line_items', ['bill_id' => $bill->id, 'description' => 'Threading', 'tax_rate' => 12]);
        $this->assertDatabaseHas('bill_line_items', ['bill_id' => $bill->id, 'description' => 'Hair Color', 'tax_rate' => 5]);
    }

    public function test_running_the_same_correction_twice_changes_nothing_the_second_time(): void
    {
        $tenant = Tenant::factory()->create();
        $this->billFor($tenant, [['description' => 'Hair Color', 'unit_price' => 1000, 'tax_rate' => 18]]);
        $service = app(BillGstRateCorrectionService::class);

        $service->correct('18', '5', Carbon::today(), Carbon::today(), true);
        $second = $service->correct('18', '5', Carbon::today(), Carbon::today(), true);

        $this->assertSame(0, $second['bills']);
    }
}
