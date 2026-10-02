<?php

namespace Tests\Feature\Billing;

use App\Models\Bill;
use App\Models\Client;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsTenant;
use Tests\TestCase;

class ExportBillsTest extends TestCase
{
    use ActsAsTenant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenant();
        $this->seed(PermissionSeeder::class);
    }

    public function test_front_desk_can_download_bills_as_xlsx(): void
    {
        $frontDesk = User::factory()->for($this->tenant)->create();
        $frontDesk->assignRole('FrontDesk');
        $client = Client::factory()->create(['tenant_id' => $this->tenant->id]);
        Bill::factory()->create(['tenant_id' => $this->tenant->id, 'client_id' => $client->id, 'created_at' => '2026-01-10']);

        $response = $this->actingAs($frontDesk)->getFromTenant('/bills/export?from_date=2026-01-01&to_date=2026-01-31');

        $response->assertOk();
        $response->assertDownload('bills-2026-01-01-to-2026-01-31.xlsx');
    }

    public function test_index_page_shows_export_link_carrying_filters(): void
    {
        $frontDesk = User::factory()->for($this->tenant)->create();
        $frontDesk->assignRole('FrontDesk');

        $response = $this->actingAs($frontDesk)->getFromTenant('/bills?from_date=2026-01-01&to_date=2026-01-31&client_name=Divya');

        $response->assertSee('Export to Excel');
        $response->assertSee('client_name=Divya', false);
    }

    public function test_guest_cannot_export_bills(): void
    {
        $response = $this->getFromTenant('/bills/export');

        $response->assertRedirect();
    }

    public function test_user_without_billing_view_cannot_export_bills(): void
    {
        $user = User::factory()->for($this->tenant)->create();

        $response = $this->actingAs($user)->getFromTenant('/bills/export');

        $response->assertForbidden();
    }
}
