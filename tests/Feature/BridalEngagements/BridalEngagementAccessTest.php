<?php

namespace Tests\Feature\BridalEngagements;

use App\Models\BridalEngagement;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsTenant;
use Tests\TestCase;

class BridalEngagementAccessTest extends TestCase
{
    use ActsAsTenant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenant();
        $this->seed(PermissionSeeder::class);
    }

    public function test_front_desk_can_access_engagement_index(): void
    {
        $frontDesk = User::factory()->for($this->tenant)->create();
        $frontDesk->assignRole('FrontDesk');

        $response = $this->actingAs($frontDesk)->getFromTenant('/bridal-engagements');

        $response->assertOk();
    }

    public function test_stylist_cannot_create_engagement(): void
    {
        $stylist = User::factory()->for($this->tenant)->create();
        $stylist->assignRole('Stylist');

        $response = $this->actingAs($stylist)->getFromTenant('/bridal-engagements/create');

        $response->assertForbidden();
    }

    public function test_stylist_cannot_edit_or_delete_engagement(): void
    {
        $stylist = User::factory()->for($this->tenant)->create();
        $stylist->assignRole('Stylist');
        $engagement = BridalEngagement::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAs($stylist)->getFromTenant("/bridal-engagements/{$engagement->id}/edit")->assertForbidden();
        $this->actingAs($stylist)->deleteFromTenant("/bridal-engagements/{$engagement->id}")->assertForbidden();
    }

    public function test_stylist_cannot_attach_bills(): void
    {
        $stylist = User::factory()->for($this->tenant)->create();
        $stylist->assignRole('Stylist');
        $engagement = BridalEngagement::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAs($stylist)->postToTenant("/bridal-engagements/{$engagement->id}/bills")->assertForbidden();
    }

    public function test_owner_can_delete_engagement(): void
    {
        $owner = User::factory()->for($this->tenant)->create();
        $owner->assignRole('Owner');
        $engagement = BridalEngagement::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAs($owner)->deleteFromTenant("/bridal-engagements/{$engagement->id}")->assertRedirect();

        $this->assertSoftDeleted('bridal_engagements', ['id' => $engagement->id]);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->getFromTenant('/bridal-engagements');

        $response->assertRedirect($this->tenantUrl('/login'));
    }
}
