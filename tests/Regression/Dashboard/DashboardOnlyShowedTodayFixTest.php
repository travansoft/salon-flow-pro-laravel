<?php

namespace Tests\Regression\Dashboard;

use App\Models\Bill;
use App\Models\Client;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\ActsAsTenant;
use Tests\TestCase;

class DashboardOnlyShowedTodayFixTest extends TestCase
{
    use ActsAsTenant, RefreshDatabase;

    public function test_dashboard_revenue_and_footfall_can_cover_more_than_one_day(): void
    {
        $this->setUpTenant();
        $this->seed(PermissionSeeder::class);
        $owner = User::factory()->for($this->tenant)->create();
        $owner->assignRole('Owner');

        foreach ([0, 1] as $daysAgo) {
            Bill::factory()->create([
                'tenant_id' => $this->tenant->id,
                'client_id' => Client::factory()->create(['tenant_id' => $this->tenant->id])->id,
                'created_by' => $owner->id,
                'total' => 250,
                'created_at' => Carbon::today()->subDays($daysAgo)->addHours(12),
            ]);
        }

        $from = Carbon::today()->subDay()->format('Y-m-d');
        $to = Carbon::today()->format('Y-m-d');

        $response = $this->actingAs($owner)->getFromTenant("/dashboard?period=custom&from={$from}&to={$to}");

        $response->assertViewHas('revenue', '500.00');
        $response->assertViewHas('customerCount', 2);
    }
}
