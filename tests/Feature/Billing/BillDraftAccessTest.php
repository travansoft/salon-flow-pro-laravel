<?php

namespace Tests\Feature\Billing;

use App\Models\BillDraft;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsTenant;
use Tests\TestCase;

class BillDraftAccessTest extends TestCase
{
    use ActsAsTenant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenant();
        $this->seed(PermissionSeeder::class);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->for($this->tenant)->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_front_desk_can_save_a_draft(): void
    {
        $user = $this->userWithRole('FrontDesk');

        $response = $this->actingAs($user)->postToTenant('/bill-drafts', ['client_name' => 'Asha']);

        $response->assertOk();
    }

    public function test_stylist_cannot_save_a_draft(): void
    {
        $stylist = $this->userWithRole('Stylist');

        $response = $this->actingAs($stylist)->postToTenant('/bill-drafts', ['client_name' => 'Asha']);

        $response->assertForbidden();
    }

    public function test_stylist_cannot_discard_a_draft(): void
    {
        $stylist = $this->userWithRole('Stylist');
        $draft = BillDraft::factory()->create(['tenant_id' => $this->tenant->id, 'user_id' => $stylist->id]);

        $response = $this->actingAs($stylist)->deleteFromTenant("/bill-drafts/{$draft->id}");

        $response->assertForbidden();
        $this->assertDatabaseHas('bill_drafts', ['id' => $draft->id]);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->postToTenant('/bill-drafts', ['client_name' => 'Asha']);

        $response->assertRedirect($this->tenantUrl('/login'));
    }
}
