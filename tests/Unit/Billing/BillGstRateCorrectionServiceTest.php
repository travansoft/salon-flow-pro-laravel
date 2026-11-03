<?php

namespace Tests\Unit\Billing;

use App\Models\Tenant;
use App\Services\BillGstRateCorrectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreatesInclusiveBills;
use Tests\TestCase;

class BillGstRateCorrectionServiceTest extends TestCase
{
    use CreatesInclusiveBills, RefreshDatabase;

    public function test_correction_restates_gst_at_the_new_rate_and_keeps_the_total(): void
    {
        $tenant = Tenant::factory()->create();
        $bill = $this->billFor($tenant, [['description' => 'Hair Color', 'unit_price' => 1000, 'tax_rate' => 18]]);

        app(BillGstRateCorrectionService::class)->correct('18', '5', Carbon::today(), Carbon::today(), true);

        $bill->refresh();
        $this->assertSame('952.38', (string) $bill->subtotal);
        $this->assertSame('47.62', (string) $bill->tax_amount);
        $this->assertSame('1000.00', (string) $bill->total);
    }

    public function test_preview_reports_changes_without_writing_them(): void
    {
        $tenant = Tenant::factory()->create();
        $bill = $this->billFor($tenant, [['description' => 'Hair Color', 'unit_price' => 1000, 'tax_rate' => 18]]);

        $result = app(BillGstRateCorrectionService::class)->correct('18', '5', Carbon::today(), Carbon::today(), false);

        $this->assertSame(1, $result['bills']);
        $this->assertSame('152.55', $result['taxBefore']);
        $this->assertSame('47.62', $result['taxAfter']);
        $this->assertSame('152.55', (string) $bill->refresh()->tax_amount);
    }

    public function test_bills_made_with_gst_added_on_top_are_left_alone(): void
    {
        $tenant = Tenant::factory()->create(['gst_inclusive' => false]);
        $bill = $this->billFor($tenant, [['description' => 'Hair Color', 'unit_price' => 1000, 'tax_rate' => 18]]);

        $result = app(BillGstRateCorrectionService::class)->correct('18', '5', Carbon::today(), Carbon::today(), true);

        $this->assertSame(0, $result['bills']);
        $this->assertSame('180.00', (string) $bill->refresh()->tax_amount);
    }

    public function test_cancelled_bills_are_left_alone(): void
    {
        $tenant = Tenant::factory()->create();
        $bill = $this->billFor($tenant, [['description' => 'Hair Color', 'unit_price' => 1000, 'tax_rate' => 18]]);
        $bill->update(['status' => 'void']);

        $result = app(BillGstRateCorrectionService::class)->correct('18', '5', Carbon::today(), Carbon::today(), true);

        $this->assertSame(0, $result['bills']);
    }
}
