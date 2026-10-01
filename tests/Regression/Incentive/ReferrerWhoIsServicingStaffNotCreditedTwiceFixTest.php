<?php

namespace Tests\Regression\Incentive;

use App\Models\StaffProfile;
use App\Services\IncentiveService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\ActsAsTenant;
use Tests\Concerns\CreatesIncentiveBills;
use Tests\TestCase;

class ReferrerWhoIsServicingStaffNotCreditedTwiceFixTest extends TestCase
{
    use ActsAsTenant, CreatesIncentiveBills, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenant();
        $this->useTenantAndBranchContext();
    }

    public function test_staff_who_refer_their_own_service_are_credited_the_value_once(): void
    {
        $staff = StaffProfile::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->billWithLine('10000', $staff, $staff);

        $row = app(IncentiveService::class)->progressForAll(Carbon::parse('2026-06-15'))->get($staff->id);

        $this->assertSame('10000.00', $row['achieved']);
        $this->assertSame('0.00', $row['referralCredit']);
    }

    public function test_split_credits_always_add_up_to_the_service_value_exactly(): void
    {
        $servicing = StaffProfile::factory()->create(['tenant_id' => $this->tenant->id]);
        $referrer = StaffProfile::factory()->create(['tenant_id' => $this->tenant->id]);
        $values = ['99.99', '100.01', '333.33', '1.01', '12345.67'];

        foreach ($values as $value) {
            $this->billWithLine($value, $servicing, $referrer);
        }

        $progress = app(IncentiveService::class)->progressForAll(Carbon::parse('2026-06-15'));
        $credited = bcadd($progress->get($servicing->id)['achieved'], $progress->get($referrer->id)['achieved'], 2);

        $this->assertSame('12880.01', $credited);
    }
}
