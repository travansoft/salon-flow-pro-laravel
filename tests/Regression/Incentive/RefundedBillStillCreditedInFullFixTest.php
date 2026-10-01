<?php

namespace Tests\Regression\Incentive;

use App\Models\StaffProfile;
use App\Services\IncentiveService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\ActsAsTenant;
use Tests\Concerns\CreatesIncentiveBills;
use Tests\TestCase;

class RefundedBillStillCreditedInFullFixTest extends TestCase
{
    use ActsAsTenant, CreatesIncentiveBills, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenant();
        $this->useTenantAndBranchContext();
    }

    public function test_a_fully_refunded_bill_no_longer_credits_servicing_or_referring_staff(): void
    {
        $servicing = StaffProfile::factory()->create(['tenant_id' => $this->tenant->id]);
        $referrer = StaffProfile::factory()->create(['tenant_id' => $this->tenant->id]);
        $bill = $this->billWithLine('10000', $servicing, $referrer);
        $bill->update(['amount_refunded' => $bill->total]);

        $progress = app(IncentiveService::class)->progressForAll(Carbon::parse('2026-06-15'));

        $this->assertSame('0.00', $progress->get($servicing->id)['achieved']);
        $this->assertSame('0.00', $progress->get($referrer->id)['achieved']);
    }

    public function test_refunds_larger_than_the_bill_never_make_credit_negative(): void
    {
        $staff = StaffProfile::factory()->create(['tenant_id' => $this->tenant->id]);
        $bill = $this->billWithLine('10000', $staff);
        $bill->update(['amount_refunded' => $bill->total + 100]);

        $progress = app(IncentiveService::class)->progressForAll(Carbon::parse('2026-06-15'));

        $this->assertSame('0.00', $progress->get($staff->id)['achieved']);
    }
}
