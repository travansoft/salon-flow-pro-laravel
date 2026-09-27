<?php

namespace Tests\Feature\Reports;

use App\Models\Bill;
use App\Models\BillLineItem;
use App\Models\Branch;
use App\Models\User;
use App\Services\BranchContext;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsTenant;
use Tests\TestCase;

class ViewConsolidatedReportsTest extends TestCase
{
    use ActsAsTenant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenant();
        $this->seed(PermissionSeeder::class);
    }

    public function test_owner_can_view_consolidated_reports(): void
    {
        $owner = User::factory()->for($this->tenant)->create();
        $owner->assignRole('Owner');

        $response = $this->actingAs($owner)->getFromTenant('/reports/consolidated');

        $response->assertOk();
        $response->assertViewIs('admin.reports.consolidated');
    }

    public function test_stylist_cannot_view_consolidated_reports(): void
    {
        $stylist = User::factory()->for($this->tenant)->create();
        $stylist->assignRole('Stylist');

        $response = $this->actingAs($stylist)->getFromTenant('/reports/consolidated');

        $response->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->getFromTenant('/reports/consolidated');

        $response->assertRedirect($this->tenantUrl('/login'));
    }

    public function test_consolidated_revenue_sums_bills_across_branches(): void
    {
        $owner = User::factory()->for($this->tenant)->create();
        $owner->assignRole('Owner');

        $branchA = Branch::factory()->create(['tenant_id' => $this->tenant->id]);
        $branchB = Branch::factory()->create(['tenant_id' => $this->tenant->id]);

        app(BranchContext::class)->set($branchA);
        $billA = Bill::factory()->paid()->create(['tenant_id' => $this->tenant->id, 'branch_id' => $branchA->id, 'total' => 500]);
        BillLineItem::factory()->create(['tenant_id' => $this->tenant->id, 'branch_id' => $branchA->id, 'bill_id' => $billA->id, 'line_total' => 500]);

        app(BranchContext::class)->set($branchB);
        $billB = Bill::factory()->paid()->create(['tenant_id' => $this->tenant->id, 'branch_id' => $branchB->id, 'total' => 300]);
        BillLineItem::factory()->create(['tenant_id' => $this->tenant->id, 'branch_id' => $branchB->id, 'bill_id' => $billB->id, 'line_total' => 300]);

        $response = $this->actingAs($owner)->getFromTenant('/reports/consolidated?period=today');

        $response->assertOk();
        $response->assertSee('800.00', false);
    }
}
