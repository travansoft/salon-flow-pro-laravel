<?php

namespace Tests\Feature\Reports;

use App\Models\Bill;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsTenant;
use Tests\TestCase;

class GstReportTest extends TestCase
{
    use ActsAsTenant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenant();
        $this->seed(PermissionSeeder::class);
    }

    private function owner(): User
    {
        $owner = User::factory()->for($this->tenant)->create();
        $owner->assignRole('Owner');

        return $owner;
    }

    public function test_owner_can_view_gst_report_for_chosen_month(): void
    {
        Bill::factory()->create(['tenant_id' => $this->tenant->id, 'created_at' => '2026-08-15 10:00:00']);

        $response = $this->actingAs($this->owner())->getFromTenant('/reports/gst?month=2026-08');

        $response->assertOk();
        $response->assertViewIs('admin.reports.gst');
        $response->assertSee('August 2026');
        $response->assertSee('590.00');
    }

    public function test_report_excludes_invoices_from_other_months(): void
    {
        Bill::factory()->create(['tenant_id' => $this->tenant->id, 'total' => 777, 'created_at' => '2026-07-31 23:00:00']);

        $response = $this->actingAs($this->owner())->getFromTenant('/reports/gst?month=2026-08');

        $response->assertOk();
        $response->assertDontSee('777.00');
        $response->assertSee('No invoices in this month.');
    }

    public function test_report_defaults_to_current_month(): void
    {
        $response = $this->actingAs($this->owner())->getFromTenant('/reports/gst');

        $response->assertOk();
        $response->assertSee(now()->format('F Y'));
    }

    public function test_invalid_month_is_rejected(): void
    {
        $response = $this->actingAs($this->owner())->getFromTenant('/reports/gst?month=garbage');

        $response->assertSessionHasErrors('month');
    }

    public function test_gst_report_is_listed_in_the_reports_menu(): void
    {
        $response = $this->actingAs($this->owner())->getFromTenant('/reports/gst');

        $response->assertSee('GST report');
    }

    public function test_owner_can_export_gst_report_as_excel(): void
    {
        Bill::factory()->create(['tenant_id' => $this->tenant->id, 'created_at' => '2026-08-15 10:00:00']);

        $response = $this->actingAs($this->owner())->getFromTenant('/reports/gst/export?month=2026-08');

        $response->assertOk();
        $response->assertDownload('gst-report-2026-08.xlsx');
    }

    public function test_user_without_permission_cannot_view_or_export(): void
    {
        $user = User::factory()->for($this->tenant)->create();

        $this->actingAs($user)->getFromTenant('/reports/gst')->assertForbidden();
        $this->actingAs($user)->getFromTenant('/reports/gst/export')->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->getFromTenant('/reports/gst')->assertRedirect('/login');
        $this->getFromTenant('/reports/gst/export')->assertRedirect('/login');
    }
}
