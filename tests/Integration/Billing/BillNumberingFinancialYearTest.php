<?php

namespace Tests\Integration\Billing;

use App\Models\Client;
use App\Models\Tenant;
use App\Models\User;
use App\Services\BillingService;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BillNumberingFinancialYearTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_bill_number_resets_to_one_in_a_new_financial_year(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $user = User::factory()->for($tenant)->create();

        Carbon::setTestNow('2026-03-31 12:00:00');
        $lastYearBill = app(BillingService::class)->createManualBill($client->id, $user->id, [
            ['description' => 'Facial', 'unit_price' => 1000],
        ]);

        Carbon::setTestNow('2026-04-01 09:00:00');
        $newYearBill = app(BillingService::class)->createManualBill($client->id, $user->id, [
            ['description' => 'Facial', 'unit_price' => 1000],
        ]);

        $this->assertSame('2025-26', $lastYearBill->financial_year);
        $this->assertSame('2026-27', $newYearBill->financial_year);
        $this->assertSame(1, $newYearBill->bill_number);
    }
}
