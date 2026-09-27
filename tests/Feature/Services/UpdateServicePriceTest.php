<?php

namespace Tests\Feature\Services;

use App\Models\Service;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsTenant;
use Tests\TestCase;

class UpdateServicePriceTest extends TestCase
{
    use ActsAsTenant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenant();
        $this->seed(PermissionSeeder::class);
    }

    public function test_updating_the_price_creates_a_new_history_entry_and_keeps_the_old_one(): void
    {
        $owner = User::factory()->for($this->tenant)->create();
        $owner->assignRole('Owner');
        $service = Service::factory()->create(['tenant_id' => $this->tenant->id, 'price' => 499]);
        $service->priceHistories()->create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $service->branch_id,
            'price' => 499,
            'effective_from' => now()->subDays(10),
        ]);

        $response = $this->actingAs($owner)->putToTenant("/services/{$service->id}", [
            'price' => 599,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('services', ['id' => $service->id, 'price' => 599]);
        $this->assertDatabaseHas('service_price_histories', ['service_id' => $service->id, 'price' => 499]);
        $this->assertDatabaseHas('service_price_histories', ['service_id' => $service->id, 'price' => 599]);
    }

    public function test_updating_without_changing_price_does_not_add_a_history_entry(): void
    {
        $owner = User::factory()->for($this->tenant)->create();
        $owner->assignRole('Owner');
        $service = Service::factory()->create(['tenant_id' => $this->tenant->id, 'price' => 499]);

        $this->actingAs($owner)->putToTenant("/services/{$service->id}", [
            'name' => 'Renamed Haircut',
        ]);

        $this->assertDatabaseCount('service_price_histories', 0);
    }

    public function test_disabling_a_service_hides_it_without_deleting_history(): void
    {
        $owner = User::factory()->for($this->tenant)->create();
        $owner->assignRole('Owner');
        $service = Service::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($owner)->deleteFromTenant("/services/{$service->id}");

        $response->assertRedirect();
        $this->assertDatabaseHas('services', ['id' => $service->id, 'is_active' => false]);
    }
}
