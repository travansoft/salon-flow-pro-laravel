<?php

namespace Tests\Feature\Services;

use App\Models\Service;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\ActsAsTenant;
use Tests\Concerns\CreatesCombos;
use Tests\TestCase;

class ComboServiceTest extends TestCase
{
    use ActsAsTenant, CreatesCombos, RefreshDatabase;

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

    /** @return array<string, mixed> */
    private function comboPayload(array $overrides = []): array
    {
        $first = Service::factory()->create(['tenant_id' => $this->tenant->id]);
        $second = Service::factory()->create(['tenant_id' => $this->tenant->id]);

        return [
            'name' => 'Glow Combo',
            'duration_minutes' => 90,
            'is_combo' => 1,
            'combo_items' => [
                ['service_id' => $first->id, 'price' => 600],
                ['service_id' => $second->id, 'price' => 400],
            ],
            ...$overrides,
        ];
    }

    public function test_owner_can_create_a_combo_priced_at_the_sum_of_its_services(): void
    {
        $response = $this->actingAs($this->owner())->postToTenant('/services', $this->comboPayload());

        $response->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('services', ['name' => 'Glow Combo', 'is_combo' => true, 'price' => 1000]);
        $this->assertDatabaseCount('service_combo_items', 2);
    }

    public function test_owner_can_override_the_combo_price(): void
    {
        $this->actingAs($this->owner())->postToTenant('/services', $this->comboPayload(['price' => 850]));

        $this->assertDatabaseHas('services', ['name' => 'Glow Combo', 'price' => 850]);
    }

    public function test_combo_needs_at_least_two_services(): void
    {
        $payload = $this->comboPayload();
        $payload['combo_items'] = [$payload['combo_items'][0]];

        $response = $this->actingAs($this->owner())->postToTenant('/services', $payload);

        $response->assertSessionHasErrors('combo_items');
        $this->assertDatabaseMissing('services', ['name' => 'Glow Combo']);
    }

    public function test_a_combo_cannot_contain_another_combo(): void
    {
        $nested = $this->comboWith([100, 200]);
        $payload = $this->comboPayload();
        $payload['combo_items'][0]['service_id'] = $nested->id;

        $response = $this->actingAs($this->owner())->postToTenant('/services', $payload);

        $response->assertSessionHasErrors('combo_items.0.service_id');
    }

    public function test_a_combo_cannot_contain_an_inactive_service(): void
    {
        $payload = $this->comboPayload();
        DB::table('services')->where('id', $payload['combo_items'][0]['service_id'])->update(['is_active' => false]);

        $response = $this->actingAs($this->owner())->postToTenant('/services', $payload);

        $response->assertSessionHasErrors('combo_items.0.service_id');
    }

    public function test_combo_services_must_be_missing_a_price_to_be_rejected(): void
    {
        $payload = $this->comboPayload();
        unset($payload['combo_items'][0]['price']);

        $response = $this->actingAs($this->owner())->postToTenant('/services', $payload);

        $response->assertSessionHasErrors('combo_items.0.price');
    }

    public function test_plain_service_still_requires_a_price(): void
    {
        $response = $this->actingAs($this->owner())->postToTenant('/services', ['name' => 'Facial', 'duration_minutes' => 30]);

        $response->assertSessionHasErrors('price');
    }

    public function test_owner_can_edit_combo_components(): void
    {
        $combo = $this->comboWith([500, 300]);
        $replacement = Service::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($this->owner())->putToTenant("/services/{$combo->id}", [
            'is_combo' => 1,
            'combo_items' => [
                ['service_id' => $combo->comboItems[0]->component_service_id, 'price' => 450],
                ['service_id' => $replacement->id, 'price' => 150],
            ],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('services', ['id' => $combo->id, 'price' => 600]);
        $this->assertDatabaseHas('service_combo_items', ['combo_service_id' => $combo->id, 'component_service_id' => $replacement->id]);
        $this->assertDatabaseCount('service_combo_items', 2);
    }

    public function test_combo_pages_render_components(): void
    {
        $combo = $this->comboWith([500, 300]);
        $owner = $this->owner();

        $this->actingAs($owner)->getFromTenant('/services')->assertOk()->assertSee('Combo');
        $this->actingAs($owner)->getFromTenant("/services/{$combo->id}")->assertOk()->assertSee($combo->comboItems[0]->component->name);
        $this->actingAs($owner)->getFromTenant("/services/{$combo->id}/edit")->assertOk();
        $this->actingAs($owner)->getFromTenant('/services/create')->assertOk();
    }

    public function test_search_returns_combo_components_for_the_pos(): void
    {
        $combo = $this->comboWith([500, 300]);
        $combo->update(['name' => 'Searchable Combo', 'code' => 'CMB1']);
        $frontDesk = User::factory()->for($this->tenant)->create();
        $frontDesk->assignRole('FrontDesk');

        $response = $this->actingAs($frontDesk)->getFromTenant('/services/search?q=Searchable');

        $response->assertOk()->assertJsonPath('services.0.is_combo', true)->assertJsonCount(2, 'services.0.components');
    }

    public function test_front_desk_cannot_create_a_combo(): void
    {
        $frontDesk = User::factory()->for($this->tenant)->create();
        $frontDesk->assignRole('FrontDesk');

        $response = $this->actingAs($frontDesk)->postToTenant('/services', $this->comboPayload());

        $response->assertForbidden();
    }

    public function test_stylist_cannot_edit_a_combo(): void
    {
        $combo = $this->comboWith([500, 300]);
        $stylist = User::factory()->for($this->tenant)->create();
        $stylist->assignRole('Stylist');

        $response = $this->actingAs($stylist)->putToTenant("/services/{$combo->id}", ['name' => 'Hacked']);

        $response->assertForbidden();
    }

    public function test_guest_is_redirected_to_login_when_creating_a_combo(): void
    {
        $response = $this->postToTenant('/services', $this->comboPayload());

        $response->assertRedirect($this->tenantUrl('/login'));
    }
}
