<?php

namespace Tests\Feature\Billing;

use App\Models\Bill;
use App\Models\Client;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsTenant;
use Tests\TestCase;

class BillListFilterTest extends TestCase
{
    use ActsAsTenant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenant();
        $this->seed(PermissionSeeder::class);
    }

    public function test_bills_can_be_filtered_by_date_range(): void
    {
        $frontDesk = User::factory()->for($this->tenant)->create();
        $frontDesk->assignRole('FrontDesk');

        $inRangeClient = Client::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Divya Menon']);
        $outOfRangeClient = Client::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Sarath Kumar']);

        Bill::factory()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $inRangeClient->id,
            'created_at' => '2026-01-10',
        ]);
        Bill::factory()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $outOfRangeClient->id,
            'created_at' => '2026-02-01',
        ]);

        $response = $this->actingAs($frontDesk)->getFromTenant('/bills?from_date=2026-01-01&to_date=2026-01-31');

        $response->assertOk();
        $response->assertSee('Divya Menon');
        $response->assertDontSee('Sarath Kumar');
    }

    public function test_bills_can_be_filtered_by_client_name(): void
    {
        $frontDesk = User::factory()->for($this->tenant)->create();
        $frontDesk->assignRole('FrontDesk');

        $matchingClient = Client::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Divya Menon']);
        $otherClient = Client::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Sarath Kumar']);

        Bill::factory()->create(['tenant_id' => $this->tenant->id, 'client_id' => $matchingClient->id]);
        Bill::factory()->create(['tenant_id' => $this->tenant->id, 'client_id' => $otherClient->id]);

        $response = $this->actingAs($frontDesk)->getFromTenant('/bills?client_name=divya');

        $response->assertOk();
        $response->assertSee('Divya Menon');
        $response->assertDontSee('Sarath Kumar');
    }

    public function test_bills_can_be_filtered_by_client_phone(): void
    {
        $frontDesk = User::factory()->for($this->tenant)->create();
        $frontDesk->assignRole('FrontDesk');

        $matchingClient = Client::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Divya Menon', 'phone' => '9876543210']);
        $otherClient = Client::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Sarath Kumar', 'phone' => '9123456780']);

        Bill::factory()->create(['tenant_id' => $this->tenant->id, 'client_id' => $matchingClient->id]);
        Bill::factory()->create(['tenant_id' => $this->tenant->id, 'client_id' => $otherClient->id]);

        $response = $this->actingAs($frontDesk)->getFromTenant('/bills?client_phone=98765');

        $response->assertOk();
        $response->assertSee('Divya Menon');
        $response->assertDontSee('Sarath Kumar');
    }
}
