<?php

namespace Tests\Feature\Reports;

use App\Models\Bill;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsTenant;
use Tests\TestCase;

class SalesSummaryDateFilterTest extends TestCase
{
    use ActsAsTenant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenant();
        $this->seed(PermissionSeeder::class);
    }

    public function test_custom_date_range_only_includes_bills_inside_it(): void
    {
        $owner = User::factory()->for($this->tenant)->create();
        $owner->assignRole('Owner');

        Bill::factory()->paid()->create(['tenant_id' => $this->tenant->id, 'total' => 1111, 'created_at' => '2026-09-10 10:00:00']);
        Bill::factory()->paid()->create(['tenant_id' => $this->tenant->id, 'total' => 2222, 'created_at' => '2026-09-20 10:00:00']);

        $response = $this->actingAs($owner)->getFromTenant('/reports?from=2026-09-01&to=2026-09-15');

        $response->assertOk();
        $response->assertSee('1,111.00');
        $response->assertDontSee('2,222.00');
        $response->assertSee('Footfall trend');
    }

    public function test_end_date_before_start_date_is_rejected(): void
    {
        $owner = User::factory()->for($this->tenant)->create();
        $owner->assignRole('Owner');

        $response = $this->actingAs($owner)->getFromTenant('/reports?from=2026-09-15&to=2026-09-01');

        $response->assertSessionHasErrors('to');
    }
}
