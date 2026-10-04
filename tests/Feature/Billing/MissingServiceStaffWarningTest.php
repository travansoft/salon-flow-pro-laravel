<?php

namespace Tests\Feature\Billing;

use App\Models\Bill;
use App\Models\BillLineItem;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsTenant;
use Tests\Concerns\CreatesIncentiveBills;
use Tests\TestCase;

class MissingServiceStaffWarningTest extends TestCase
{
    use ActsAsTenant, CreatesIncentiveBills, RefreshDatabase;

    private const WarningText = 'Servicing staff is not set for every service in this bill';

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenant();
        $this->seed(PermissionSeeder::class);
        $this->useTenantAndBranchContext();

        $this->owner = User::factory()->for($this->tenant)->create();
        $this->owner->assignRole('Owner');
        $this->assignToBranch($this->owner);
    }

    private function billWithServiceLine(?StaffProfile $staff): Bill
    {
        $bill = $this->billWithLine('1000', StaffProfile::factory()->create(['tenant_id' => $this->tenant->id]), createdAt: now()->toDateString());
        $bill->lineItems()->firstOrFail()->update([
            'service_id' => Service::factory()->create(['tenant_id' => $this->tenant->id])->id,
            'staff_profile_id' => $staff?->id,
        ]);

        return $bill;
    }

    public function test_bill_page_warns_when_a_service_has_no_servicing_staff(): void
    {
        $bill = $this->billWithServiceLine(null);

        $response = $this->actingAs($this->owner)->getFromTenant("/bills/{$bill->id}");

        $response->assertOk()->assertSee(self::WarningText);
    }

    public function test_bill_page_has_no_warning_when_every_service_has_staff(): void
    {
        $bill = $this->billWithServiceLine(StaffProfile::factory()->create(['tenant_id' => $this->tenant->id]));

        $response = $this->actingAs($this->owner)->getFromTenant("/bills/{$bill->id}");

        $response->assertOk()->assertDontSee(self::WarningText);
    }

    public function test_bill_list_flags_only_the_bills_missing_service_staff(): void
    {
        $this->billWithServiceLine(null);
        $this->billWithServiceLine(StaffProfile::factory()->create(['tenant_id' => $this->tenant->id]));

        $response = $this->actingAs($this->owner)->getFromTenant('/bills');

        $response->assertOk();
        $this->assertSame(1, substr_count($response->getContent(), self::WarningText.'" aria-label'));
    }

    public function test_manual_line_without_staff_does_not_trigger_the_warning(): void
    {
        $bill = $this->billWithServiceLine(StaffProfile::factory()->create(['tenant_id' => $this->tenant->id]));
        BillLineItem::factory()->create(['tenant_id' => $this->tenant->id, 'branch_id' => $this->branch->id, 'bill_id' => $bill->id, 'service_id' => null, 'staff_profile_id' => null]);

        $response = $this->actingAs($this->owner)->getFromTenant("/bills/{$bill->id}");

        $response->assertDontSee(self::WarningText);
    }
}
