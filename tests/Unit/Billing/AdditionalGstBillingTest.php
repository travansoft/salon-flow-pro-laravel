<?php

namespace Tests\Unit\Billing;

use App\Models\Branch;
use App\Models\Client;
use App\Models\Tenant;
use App\Models\User;
use App\Services\BillingService;
use App\Services\BranchContext;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class AdditionalGstBillingTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: BillingService, 1: Client, 2: User} */
    private function billingFor(bool $gstInclusive, float $rate = 18): array
    {
        $tenant = Tenant::factory()->create(['gst_inclusive' => $gstInclusive, 'default_gst_rate' => $rate]);
        app(TenantContext::class)->set($tenant);
        app(BranchContext::class)->set(Branch::factory()->create(['tenant_id' => $tenant->id]));

        return [
            app(BillingService::class),
            Client::factory()->create(['tenant_id' => $tenant->id]),
            User::factory()->for($tenant)->create(),
        ];
    }

    public function test_additional_gst_is_added_on_top_of_the_price(): void
    {
        [$service, $client, $user] = $this->billingFor(false);

        $bill = $service->createManualBill($client->id, $user->id, [
            ['description' => 'Hair Color', 'unit_price' => 1000, 'tax_rate' => 18],
        ]);

        $this->assertSame('1000.00', (string) $bill->subtotal);
        $this->assertSame('180.00', (string) $bill->tax_amount);
        $this->assertSame('1180.00', (string) $bill->total);
        $this->assertFalse($bill->prices_include_gst);
    }

    public function test_inclusive_gst_keeps_the_price_as_the_total(): void
    {
        [$service, $client, $user] = $this->billingFor(true);

        $bill = $service->createManualBill($client->id, $user->id, [
            ['description' => 'Hair Color', 'unit_price' => 1000, 'tax_rate' => 18],
        ]);

        $this->assertSame('1000.00', (string) $bill->total);
        $this->assertTrue($bill->prices_include_gst);
    }

    public function test_additional_gst_uses_the_tenant_default_rate_when_line_has_none(): void
    {
        [$service, $client, $user] = $this->billingFor(false, 5);

        $bill = $service->createManualBill($client->id, $user->id, [
            ['description' => 'Facial', 'unit_price' => 200],
        ]);

        $this->assertSame('10.00', (string) $bill->tax_amount);
        $this->assertSame('210.00', (string) $bill->total);
    }

    public function test_additional_gst_splits_into_cgst_and_sgst(): void
    {
        [$service, $client, $user] = $this->billingFor(false);

        $bill = $service->createManualBill($client->id, $user->id, [
            ['description' => 'Hair Color', 'unit_price' => 1000, 'tax_rate' => 18],
        ]);

        $this->assertSame('90.00', (string) $bill->cgst_amount);
        $this->assertSame('90.00', (string) $bill->sgst_amount);
    }

    public function test_additional_gst_is_charged_on_the_discounted_price(): void
    {
        [$service, $client, $user] = $this->billingFor(false);

        $bill = $service->createManualBill($client->id, $user->id, [
            ['description' => 'Hair Color', 'unit_price' => 1000, 'tax_rate' => 18],
        ], discountPercent: 10);

        $this->assertSame('100.00', (string) $bill->discount_amount);
        $this->assertSame('162.00', (string) $bill->tax_amount);
        $this->assertSame('1062.00', (string) $bill->total);
    }

    public function test_additional_gst_multiplies_by_quantity(): void
    {
        [$service, $client, $user] = $this->billingFor(false);

        $bill = $service->createManualBill($client->id, $user->id, [
            ['description' => 'Threading', 'unit_price' => 50, 'quantity' => 3, 'tax_rate' => 18],
        ]);

        $this->assertSame('150.00', (string) $bill->subtotal);
        $this->assertSame('27.00', (string) $bill->tax_amount);
    }

    public function test_discount_amount_cannot_exceed_the_exclusive_subtotal(): void
    {
        [$service, $client, $user] = $this->billingFor(false);

        $this->expectException(InvalidArgumentException::class);

        $service->createManualBill($client->id, $user->id, [
            ['description' => 'Hair Color', 'unit_price' => 1000, 'tax_rate' => 18],
        ], discountAmount: 1100);
    }
}
