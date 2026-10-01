<?php

namespace Tests\Feature\Incentive;

use App\Models\IncentiveSetting;
use App\Models\IncentiveSlab;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsTenant;
use Tests\TestCase;

class IncentiveSettingsTest extends TestCase
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

    public function test_first_visit_creates_the_default_split_and_slabs(): void
    {
        $response = $this->actingAs($this->owner)->getFromTenant('/incentive-settings');

        $response->assertOk();
        $this->assertDatabaseHas('incentive_settings', [
            'tenant_id' => $this->tenant->id,
            'servicing_share_percent' => 70,
            'referring_share_percent' => 30,
        ]);
        $this->assertDatabaseCount('incentive_slabs', 3);
        $this->assertDatabaseHas('incentive_slabs', ['tenant_id' => $this->tenant->id, 'min_achievement_percent' => 90, 'incentive_percent' => 4]);
    }

    public function test_owner_can_change_the_referral_split(): void
    {
        $response = $this->actingAs($this->owner)->putToTenant('/incentive-settings', [
            'servicing_share_percent' => 60,
            'referring_share_percent' => 40,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('incentive_settings', [
            'tenant_id' => $this->tenant->id,
            'servicing_share_percent' => 60,
            'referring_share_percent' => 40,
        ]);
    }

    public function test_split_that_does_not_add_up_to_one_hundred_is_rejected(): void
    {
        $response = $this->actingAs($this->owner)->putToTenant('/incentive-settings', [
            'servicing_share_percent' => 60,
            'referring_share_percent' => 30,
        ]);

        $response->assertSessionHasErrors('referring_share_percent');
    }

    public function test_split_percentages_must_be_between_zero_and_one_hundred(): void
    {
        $response = $this->actingAs($this->owner)->putToTenant('/incentive-settings', [
            'servicing_share_percent' => 130,
            'referring_share_percent' => -30,
        ]);

        $response->assertSessionHasErrors(['servicing_share_percent', 'referring_share_percent']);
    }

    public function test_owner_can_add_a_slab(): void
    {
        $this->actingAs($this->owner)->getFromTenant('/incentive-settings');

        $response = $this->actingAs($this->owner)->postToTenant('/incentive-slabs', [
            'min_achievement_percent' => 110,
            'incentive_percent' => 6,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('incentive_slabs', ['tenant_id' => $this->tenant->id, 'min_achievement_percent' => 110, 'incentive_percent' => 6]);
    }

    public function test_duplicate_slab_threshold_is_rejected(): void
    {
        $this->actingAs($this->owner)->getFromTenant('/incentive-settings');

        $response = $this->actingAs($this->owner)->postToTenant('/incentive-slabs', [
            'min_achievement_percent' => 80,
            'incentive_percent' => 9,
        ]);

        $response->assertSessionHasErrors('min_achievement_percent');
    }

    public function test_owner_can_update_a_slab_keeping_its_own_threshold(): void
    {
        $this->actingAs($this->owner)->getFromTenant('/incentive-settings');
        $slab = IncentiveSlab::where('min_achievement_percent', 80)->firstOrFail();

        $response = $this->actingAs($this->owner)->putToTenant("/incentive-slabs/{$slab->id}", [
            'min_achievement_percent' => 80,
            'incentive_percent' => 3.5,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('incentive_slabs', ['id' => $slab->id, 'incentive_percent' => 3.5]);
    }

    public function test_owner_can_remove_a_slab(): void
    {
        $this->actingAs($this->owner)->getFromTenant('/incentive-settings');
        $slab = IncentiveSlab::where('min_achievement_percent', 100)->firstOrFail();

        $response = $this->actingAs($this->owner)->deleteFromTenant("/incentive-slabs/{$slab->id}");

        $response->assertRedirect();
        $this->assertDatabaseMissing('incentive_slabs', ['id' => $slab->id]);
    }

    public function test_another_tenants_slab_cannot_be_changed(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherSlab = IncentiveSlab::factory()->create(['tenant_id' => $otherTenant->id, 'incentive_percent' => 4]);

        $response = $this->actingAs($this->owner)->putToTenant("/incentive-slabs/{$otherSlab->id}", [
            'min_achievement_percent' => 50,
            'incentive_percent' => 99,
        ]);

        $response->assertNotFound();
        $this->assertDatabaseHas('incentive_slabs', ['id' => $otherSlab->id, 'incentive_percent' => 4]);
    }

    public function test_settings_are_isolated_per_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        IncentiveSetting::factory()->create(['tenant_id' => $otherTenant->id, 'servicing_share_percent' => 50, 'referring_share_percent' => 50]);

        $this->actingAs($this->owner)->getFromTenant('/incentive-settings')->assertOk();

        $this->assertDatabaseHas('incentive_settings', ['tenant_id' => $this->tenant->id, 'servicing_share_percent' => 70]);
        $this->assertDatabaseHas('incentive_settings', ['tenant_id' => $otherTenant->id, 'servicing_share_percent' => 50]);
    }
}
