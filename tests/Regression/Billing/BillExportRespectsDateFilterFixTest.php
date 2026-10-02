<?php

namespace Tests\Regression\Billing;

use App\Models\Bill;
use App\Models\Client;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsTenant;
use Tests\TestCase;
use ZipArchive;

class BillExportRespectsDateFilterFixTest extends TestCase
{
    use ActsAsTenant, RefreshDatabase;

    public function test_bill_export_no_longer_includes_bills_outside_the_selected_dates(): void
    {
        $this->setUpTenant();
        $this->seed(PermissionSeeder::class);
        $user = User::factory()->for($this->tenant)->create();
        $user->assignRole('FrontDesk');
        $client = Client::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Outside Range']);
        Bill::factory()->create(['tenant_id' => $this->tenant->id, 'client_id' => $client->id, 'created_at' => '2026-03-01']);

        $response = $this->actingAs($user)->getFromTenant('/bills/export?from_date=2026-01-01&to_date=2026-01-31');

        $zip = new ZipArchive;
        $zip->open($response->baseResponse->getFile()->getPathname());
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        $this->assertStringNotContainsString('Outside Range', $sheet);
    }
}
