<?php

namespace Tests\Feature\Dashboard;

use App\Models\Bill;
use App\Models\Client;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\ActsAsTenant;
use Tests\TestCase;

class DashboardRangeTest extends TestCase
{
    use ActsAsTenant, RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenant();
        $this->seed(PermissionSeeder::class);

        $this->owner = User::factory()->for($this->tenant)->create();
        $this->owner->assignRole('Owner');
    }

    private function createBill(float $total, Carbon $at): void
    {
        $client = Client::factory()->create(['tenant_id' => $this->tenant->id]);

        Bill::factory()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $client->id,
            'created_by' => $this->owner->id,
            'total' => $total,
            'created_at' => $at,
        ]);
    }

    public function test_week_period_includes_bills_since_start_of_week(): void
    {
        $this->createBill(100, Carbon::today()->startOfWeek()->addHours(10));
        $this->createBill(50, Carbon::today()->startOfWeek()->subDay());

        $response = $this->actingAs($this->owner)->getFromTenant('/dashboard?period=week');

        $response->assertOk();
        $response->assertViewHas('revenue', '100.00');
        $response->assertViewHas('customerCount', 1);
    }

    public function test_month_period_includes_bills_since_start_of_month(): void
    {
        $this->createBill(300, Carbon::today()->startOfMonth()->addHours(9));
        $this->createBill(70, Carbon::today()->startOfMonth()->subDay());

        $response = $this->actingAs($this->owner)->getFromTenant('/dashboard?period=month');

        $response->assertViewHas('revenue', '300.00');
    }

    public function test_custom_range_filters_revenue_and_footfall(): void
    {
        $this->createBill(120, Carbon::parse('2026-03-10 11:00'));
        $this->createBill(80, Carbon::parse('2026-03-12 15:00'));
        $this->createBill(999, Carbon::parse('2026-03-20 15:00'));

        $response = $this->actingAs($this->owner)
            ->getFromTenant('/dashboard?period=custom&from=2026-03-10&to=2026-03-12');

        $response->assertViewHas('revenue', '200.00');
        $response->assertViewHas('customerCount', 2);
    }

    public function test_week_period_shows_revenue_and_footfall_trends_per_day(): void
    {
        $this->createBill(100, Carbon::today()->startOfWeek()->addHours(10));

        $response = $this->actingAs($this->owner)->getFromTenant('/dashboard?period=week');

        $response->assertSee('Revenue trend');
        $response->assertSee('Footfall trend');
    }

    public function test_today_period_hides_trend_charts_but_shows_payment_mix(): void
    {
        $response = $this->actingAs($this->owner)->getFromTenant('/dashboard');

        $response->assertDontSee('Revenue trend');
        $response->assertSee('Payment mix');
    }

    public function test_dashboard_lists_payment_methods_for_the_period(): void
    {
        $client = Client::factory()->create(['tenant_id' => $this->tenant->id]);
        $bill = Bill::factory()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $client->id,
            'created_by' => $this->owner->id,
            'total' => 500,
            'created_at' => now(),
        ]);
        $bill->payments()->create([
            'tenant_id' => $this->tenant->id,
            'method' => 'upi',
            'amount' => 500,
        ]);

        $response = $this->actingAs($this->owner)->getFromTenant('/dashboard');

        $response->assertViewHas('paymentMix', fn (array $mix): bool => $mix[0]['method'] === 'upi' && $mix[0]['amount'] === '500.00');
    }

    public function test_default_period_is_today(): void
    {
        $this->createBill(100, now());
        $this->createBill(500, now()->subDay());

        $response = $this->actingAs($this->owner)->getFromTenant('/dashboard');

        $response->assertViewHas('revenue', '100.00');
        $response->assertViewHas('period', 'today');
    }

    public function test_custom_range_requires_both_dates(): void
    {
        $response = $this->actingAs($this->owner)->getFromTenant('/dashboard?period=custom&from=2026-03-10');

        $response->assertSessionHasErrors('to');
    }

    public function test_custom_range_rejects_end_before_start(): void
    {
        $response = $this->actingAs($this->owner)
            ->getFromTenant('/dashboard?period=custom&from=2026-03-12&to=2026-03-10');

        $response->assertSessionHasErrors('to');
    }

    public function test_invalid_period_is_rejected(): void
    {
        $response = $this->actingAs($this->owner)->getFromTenant('/dashboard?period=decade');

        $response->assertSessionHasErrors('period');
    }

    public function test_guest_is_redirected_to_login_for_ranged_dashboard(): void
    {
        $response = $this->getFromTenant('/dashboard?period=week');

        $response->assertRedirect($this->tenantUrl('/login'));
    }
}
