<?php

namespace Tests\Feature\Dashboard;

use App\Models\Product;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsTenant;
use Tests\TestCase;

class DashboardLowStockLinkTest extends TestCase
{
    use ActsAsTenant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenant();
        $this->seed(PermissionSeeder::class);
    }

    public function test_owner_sees_low_stock_report_link_when_items_are_low(): void
    {
        $owner = User::factory()->for($this->tenant)->create();
        $owner->assignRole('Owner');
        Product::factory()->lowStock()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($owner)->getFromTenant('/dashboard');

        $response->assertSee('View low stock report');
        $response->assertSee($this->tenantUrl('/inventory/low-stock'));
    }

    public function test_link_is_hidden_when_nothing_is_low(): void
    {
        $owner = User::factory()->for($this->tenant)->create();
        $owner->assignRole('Owner');

        $response = $this->actingAs($owner)->getFromTenant('/dashboard');

        $response->assertDontSee('View low stock report');
    }
}
