<?php

namespace Tests\Unit\Billing;

use App\Models\Bill;
use App\Models\BillLineItem;
use App\Models\Service;
use App\Models\StaffProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsTenant;
use Tests\Concerns\CreatesIncentiveBills;
use Tests\TestCase;

class BillMissingServiceStaffTest extends TestCase
{
    use ActsAsTenant, CreatesIncentiveBills, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenant();
        $this->useTenantAndBranchContext();
    }

    private function billWith(?int $serviceId, ?int $staffId): Bill
    {
        $bill = $this->billWithLine('100', StaffProfile::factory()->create(['tenant_id' => $this->tenant->id]));
        $bill->lineItems()->firstOrFail()->update(['service_id' => $serviceId, 'staff_profile_id' => $staffId]);

        return $bill->load('lineItems');
    }

    public function test_service_line_without_staff_is_flagged(): void
    {
        $service = Service::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->assertTrue($this->billWith($service->id, null)->hasMissingServiceStaff());
    }

    public function test_service_line_with_staff_is_not_flagged(): void
    {
        $service = Service::factory()->create(['tenant_id' => $this->tenant->id]);
        $staff = StaffProfile::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->assertFalse($this->billWith($service->id, $staff->id)->hasMissingServiceStaff());
    }

    public function test_manual_line_without_staff_is_not_flagged(): void
    {
        $this->assertFalse($this->billWith(null, null)->hasMissingServiceStaff());
    }

    public function test_one_unassigned_service_among_assigned_ones_flags_the_bill(): void
    {
        $service = Service::factory()->create(['tenant_id' => $this->tenant->id]);
        $staff = StaffProfile::factory()->create(['tenant_id' => $this->tenant->id]);
        $bill = $this->billWith($service->id, $staff->id);
        BillLineItem::factory()->create(['tenant_id' => $this->tenant->id, 'branch_id' => $this->branch->id, 'bill_id' => $bill->id, 'service_id' => $service->id, 'staff_profile_id' => null]);

        $this->assertTrue($bill->load('lineItems')->hasMissingServiceStaff());
    }
}
