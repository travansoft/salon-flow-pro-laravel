<?php

namespace Tests\Integration\Billing;

use App\Models\Client;
use App\Models\Service;
use App\Models\User;
use App\Services\IncentiveService;
use App\Services\QuickBillService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\ActsAsTenant;
use Tests\Concerns\CreatesIncentiveBills;
use Tests\TestCase;

class BillWithoutServiceStaffDatabaseTest extends TestCase
{
    use ActsAsTenant, CreatesIncentiveBills, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenant();
        $this->useTenantAndBranchContext();
    }

    public function test_bill_without_service_staff_is_stored_and_flagged(): void
    {
        $service = Service::factory()->create(['tenant_id' => $this->tenant->id, 'price' => 500]);
        $client = Client::factory()->create(['tenant_id' => $this->tenant->id]);
        $user = User::factory()->for($this->tenant)->create();

        $bill = app(QuickBillService::class)->createAndSettle([['service_id' => $service->id]], ['client_id' => $client->id], 'cash', $user->id);

        $this->assertDatabaseHas('bill_line_items', ['bill_id' => $bill->id, 'staff_profile_id' => null]);
        $this->assertTrue($bill->fresh('lineItems')->hasMissingServiceStaff());
    }

    public function test_unassigned_service_credits_no_staff_target(): void
    {
        $service = Service::factory()->create(['tenant_id' => $this->tenant->id, 'price' => 500]);
        $user = User::factory()->for($this->tenant)->create();
        app(QuickBillService::class)->createAndSettle([['service_id' => $service->id]], [], 'cash', $user->id);

        $progress = app(IncentiveService::class)->progressForAll(Carbon::now());

        $this->assertSame(0, $progress->filter(fn (array $row) => bccomp($row['servicingCredit'], '0', 2) > 0)->count());
    }
}
