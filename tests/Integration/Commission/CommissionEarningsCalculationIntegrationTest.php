<?php

namespace Tests\Integration\Commission;

use App\Models\Appointment;
use App\Models\Bill;
use App\Models\BillLineItem;
use App\Models\Branch;
use App\Models\CommissionRate;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\StaffIncentive;
use App\Models\StaffProfile;
use App\Models\Tenant;
use App\Models\User;
use App\Services\BranchContext;
use App\Services\CommissionService;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CommissionEarningsCalculationIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_earnings_for_computes_correct_totals_across_multiple_bills_and_rate_tiers(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);

        $staff = StaffProfile::factory()->create(['tenant_id' => $tenant->id]);
        $hairCategory = ServiceCategory::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Hair']);
        $bridalCategory = ServiceCategory::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Bridal']);

        $haircut = Service::factory()->create(['tenant_id' => $tenant->id, 'category_id' => $hairCategory->id]);
        $bridalPackage = Service::factory()->create(['tenant_id' => $tenant->id, 'category_id' => $bridalCategory->id]);

        // Tenant-wide default: 10%
        CommissionRate::factory()->create([
            'tenant_id' => $tenant->id,
            'staff_profile_id' => null,
            'service_category_id' => null,
            'rate_percent' => 10,
            'effective_from' => '2026-01-01',
        ]);

        // Staff-specific override on Bridal: 20%
        CommissionRate::factory()->create([
            'tenant_id' => $tenant->id,
            'staff_profile_id' => $staff->id,
            'service_category_id' => $bridalCategory->id,
            'rate_percent' => 20,
            'effective_from' => '2026-01-01',
        ]);

        $appointment = Appointment::factory()->create(['tenant_id' => $tenant->id, 'staff_profile_id' => $staff->id]);

        $paidBill = Bill::factory()->create([
            'tenant_id' => $tenant->id,
            'appointment_id' => $appointment->id,
            'status' => Bill::StatusPaid,
            'created_at' => '2026-06-10',
        ]);
        BillLineItem::factory()->create([
            'tenant_id' => $tenant->id,
            'bill_id' => $paidBill->id,
            'service_id' => $haircut->id,
            'line_total' => 1000,
        ]);
        BillLineItem::factory()->create([
            'tenant_id' => $tenant->id,
            'bill_id' => $paidBill->id,
            'service_id' => $bridalPackage->id,
            'line_total' => 5000,
        ]);

        $secondAppointment = Appointment::factory()->create(['tenant_id' => $tenant->id, 'staff_profile_id' => $staff->id]);
        $secondPaidBill = Bill::factory()->create([
            'tenant_id' => $tenant->id,
            'appointment_id' => $secondAppointment->id,
            'status' => Bill::StatusPaid,
            'created_at' => '2026-06-20',
        ]);
        BillLineItem::factory()->create([
            'tenant_id' => $tenant->id,
            'bill_id' => $secondPaidBill->id,
            'service_id' => $haircut->id,
            'line_total' => 2000,
        ]);

        $unpaidAppointment = Appointment::factory()->create(['tenant_id' => $tenant->id, 'staff_profile_id' => $staff->id]);
        $unpaidBill = Bill::factory()->create([
            'tenant_id' => $tenant->id,
            'appointment_id' => $unpaidAppointment->id,
            'status' => Bill::StatusUnpaid,
            'created_at' => '2026-06-15',
        ]);
        BillLineItem::factory()->create([
            'tenant_id' => $tenant->id,
            'bill_id' => $unpaidBill->id,
            'service_id' => $bridalPackage->id,
            'line_total' => 9999,
        ]);

        $awardedBy = User::factory()->for($tenant)->create();
        StaffIncentive::factory()->create([
            'tenant_id' => $tenant->id,
            'staff_profile_id' => $staff->id,
            'amount' => 500,
            'awarded_date' => '2026-06-12',
            'awarded_by' => $awardedBy->id,
        ]);
        StaffIncentive::factory()->create([
            'tenant_id' => $tenant->id,
            'staff_profile_id' => $staff->id,
            'amount' => 300,
            'awarded_date' => '2026-05-01',
            'awarded_by' => $awardedBy->id,
        ]);

        $result = app(CommissionService::class)->earningsFor($staff, Carbon::parse('2026-06-01'), Carbon::parse('2026-06-30'));

        // 1000*10% + 5000*20% + 2000*10% = 100 + 1000 + 200 = 1300.00
        $this->assertSame('1300.00', $result['commissionEarned']);
        // Only the June incentive counts; May's is outside the period.
        $this->assertSame('500.00', $result['incentivesEarned']);
        $this->assertSame('1800.00', $result['totalEarned']);
        $this->assertSame(3, $result['lineItemCount']);
    }
}
