<?php

namespace Tests\Integration\Incentive;

use App\Models\Bill;
use App\Models\StaffIncentive;
use App\Models\StaffProfile;
use App\Models\StaffTarget;
use App\Models\Tenant;
use App\Models\User;
use App\Services\IncentiveService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\ActsAsTenant;
use Tests\Concerns\CreatesIncentiveBills;
use Tests\TestCase;

class IncentiveCreditIntegrationTest extends TestCase
{
    use ActsAsTenant, CreatesIncentiveBills, RefreshDatabase;

    private Carbon $june;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenant();
        $this->useTenantAndBranchContext();
        $this->june = Carbon::parse('2026-06-15');
    }

    private function staff(string $name = 'Staff'): StaffProfile
    {
        return StaffProfile::factory()->create(['tenant_id' => $this->tenant->id, 'name' => $name]);
    }

    /** @return array<string, mixed> */
    private function progressOf(StaffProfile $staff): array
    {
        return app(IncentiveService::class)->progressForAll($this->june)->get($staff->id);
    }

    public function test_paid_bills_credit_the_servicing_and_referring_staff_in_the_configured_split(): void
    {
        $servicing = $this->staff('Rizwan');
        $referrer = $this->staff('Azam');
        $this->billWithLine('10000', $servicing, $referrer);

        $this->assertSame('7000.00', $this->progressOf($servicing)['servicingCredit']);
        $this->assertSame('3000.00', $this->progressOf($referrer)['referralCredit']);
    }

    public function test_changing_the_split_changes_the_credit(): void
    {
        $servicing = $this->staff('Rizwan');
        $referrer = $this->staff('Azam');
        $this->billWithLine('10000', $servicing, $referrer);

        app(IncentiveService::class)->updateSettings(['servicing_share_percent' => 60, 'referring_share_percent' => 40]);

        $this->assertSame('6000.00', $this->progressOf($servicing)['servicingCredit']);
        $this->assertSame('4000.00', $this->progressOf($referrer)['referralCredit']);
    }

    public function test_credit_uses_the_gst_inclusive_value_after_discount(): void
    {
        $staff = $this->staff();
        $this->billWithLine('1000', $staff, gst: '162', discount: '100');

        $this->assertSame('1062.00', $this->progressOf($staff)['achieved']);
    }

    public function test_unpaid_partial_and_cancelled_bills_earn_no_credit(): void
    {
        $staff = $this->staff();
        $this->billWithLine('1000', $staff, status: Bill::StatusUnpaid);
        $this->billWithLine('1000', $staff, status: Bill::StatusPartial);
        $this->billWithLine('1000', $staff, status: Bill::StatusVoid);

        $this->assertSame('0.00', $this->progressOf($staff)['achieved']);
    }

    public function test_cancelling_a_paid_bill_removes_its_credit(): void
    {
        $servicing = $this->staff('Rizwan');
        $referrer = $this->staff('Azam');
        $bill = $this->billWithLine('10000', $servicing, $referrer);
        $this->assertSame('7000.00', $this->progressOf($servicing)['achieved']);

        $bill->update(['status' => Bill::StatusVoid]);

        $this->assertSame('0.00', $this->progressOf($servicing)['achieved']);
        $this->assertSame('0.00', $this->progressOf($referrer)['achieved']);
    }

    public function test_only_bills_inside_the_month_count(): void
    {
        $staff = $this->staff();
        $this->billWithLine('1000', $staff, createdAt: '2026-05-31 23:59:59');
        $this->billWithLine('2000', $staff, createdAt: '2026-06-01 00:00:00');
        $this->billWithLine('4000', $staff, createdAt: '2026-06-30 23:59:59');
        $this->billWithLine('8000', $staff, createdAt: '2026-07-01 00:00:00');

        $this->assertSame('6000.00', $this->progressOf($staff)['achieved']);
    }

    public function test_another_tenants_bills_never_count(): void
    {
        $staff = $this->staff();
        $this->billWithLine('1000', $staff);

        $otherTenant = Tenant::factory()->create();
        $otherStaff = StaffProfile::factory()->create(['tenant_id' => $otherTenant->id]);
        Bill::factory()->create(['tenant_id' => $otherTenant->id, 'status' => Bill::StatusPaid, 'created_at' => '2026-06-10']);

        $progress = app(IncentiveService::class)->progressForAll($this->june);

        $this->assertSame('1000.00', $progress->get($staff->id)['achieved']);
        $this->assertNull($progress->get($otherStaff->id));
    }

    public function test_full_month_flow_matches_the_salon_example(): void
    {
        $rizwan = $this->staff('Rizwan');
        $azam = $this->staff('Azam');
        StaffTarget::factory()->create(['tenant_id' => $this->tenant->id, 'staff_profile_id' => $rizwan->id, 'month' => '2026-06-01', 'target_amount' => 200000]);
        StaffTarget::factory()->create(['tenant_id' => $this->tenant->id, 'staff_profile_id' => $azam->id, 'month' => '2026-06-01', 'target_amount' => 150000]);

        $this->billWithLine('170000', $rizwan);
        $this->billWithLine('20000', $rizwan, $azam);
        $this->billWithLine('120000', $azam);
        StaffIncentive::factory()->create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'staff_profile_id' => $azam->id,
            'amount' => 1000,
            'awarded_date' => '2026-06-20',
            'awarded_by' => User::factory()->for($this->tenant)->create()->id,
        ]);

        $rizwanProgress = $this->progressOf($rizwan);
        $azamProgress = $this->progressOf($azam);

        $this->assertSame('184000.00', $rizwanProgress['achieved']);
        $this->assertSame('92.00', $rizwanProgress['achievementPercent']);
        $this->assertSame('7360.00', $rizwanProgress['incentive']);

        $this->assertSame('126000.00', $azamProgress['achieved']);
        $this->assertSame('84.00', $azamProgress['achievementPercent']);
        $this->assertSame('3780.00', $azamProgress['incentive']);
        $this->assertSame('4780.00', $azamProgress['totalEarned']);
    }
}
