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
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BackfillBillNumberingUsesBillDateFixTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Bug risk: backdating a bill must number and file it under the
     * financial year computed from the selected bill date, not today's date.
     * Otherwise a bill entered today for a sale made last financial year
     * would land in the wrong year's numbering sequence and reports.
     */
    public function test_backdated_bill_from_a_prior_financial_year_gets_that_years_numbering_and_not_todays(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-15'));

        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $user = User::factory()->for($tenant)->create();

        $currentYearBill = app(BillingService::class)->createManualBill($client->id, $user->id, [
            ['description' => 'Haircut', 'unit_price' => 500],
        ]);

        $backdatedBill = app(BillingService::class)->createManualBill($client->id, $user->id, [
            ['description' => 'Haircut', 'unit_price' => 500],
        ], 0, Carbon::parse('2025-02-10'));

        $this->assertSame('2026-27', $currentYearBill->financial_year);
        $this->assertSame('2024-25', $backdatedBill->financial_year);
        $this->assertSame(1, $backdatedBill->bill_number);

        Carbon::setTestNow();
    }
}
