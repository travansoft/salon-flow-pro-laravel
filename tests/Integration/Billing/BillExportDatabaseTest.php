<?php

namespace Tests\Integration\Billing;

use App\Models\Bill;
use App\Models\Client;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsTenant;
use Tests\TestCase;
use ZipArchive;

class BillExportDatabaseTest extends TestCase
{
    use ActsAsTenant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenant();
        $this->seed(PermissionSeeder::class);
    }

    private function exportedSheet(User $user, string $query): string
    {
        $response = $this->actingAs($user)->getFromTenant("/bills/export?{$query}");
        $response->assertOk();

        $zip = new ZipArchive;
        $zip->open($response->baseResponse->getFile()->getPathname());
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        return $sheet;
    }

    public function test_export_contains_only_bills_matching_the_filters(): void
    {
        $user = User::factory()->for($this->tenant)->create();
        $user->assignRole('FrontDesk');
        $matching = Client::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Divya Menon']);
        $other = Client::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Sarath Kumar']);
        Bill::factory()->create(['tenant_id' => $this->tenant->id, 'client_id' => $matching->id, 'created_at' => '2026-01-10']);
        Bill::factory()->create(['tenant_id' => $this->tenant->id, 'client_id' => $other->id, 'created_at' => '2026-01-10']);

        $sheet = $this->exportedSheet($user, 'from_date=2026-01-01&to_date=2026-01-31&client_name=Divya');

        $this->assertStringContainsString('Divya Menon', $sheet);
        $this->assertStringNotContainsString('Sarath Kumar', $sheet);
    }
}
