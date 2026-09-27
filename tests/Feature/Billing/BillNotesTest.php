<?php

namespace Tests\Feature\Billing;

use App\Models\Bill;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsTenant;
use Tests\TestCase;

class BillNotesTest extends TestCase
{
    use ActsAsTenant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenant();
        $this->seed(PermissionSeeder::class);
    }

    public function test_owner_can_add_a_note_to_a_bill(): void
    {
        $owner = User::factory()->for($this->tenant)->create();
        $owner->assignRole('Owner');
        $bill = Bill::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($owner)->putToTenant("/bills/{$bill->id}/notes", [
            'notes' => 'Client complained about wait time.',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('bills', [
            'id' => $bill->id,
            'notes' => 'Client complained about wait time.',
        ]);
    }

    public function test_front_desk_cannot_add_a_note_to_a_bill(): void
    {
        $frontDesk = User::factory()->for($this->tenant)->create();
        $frontDesk->assignRole('FrontDesk');
        $bill = Bill::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($frontDesk)->putToTenant("/bills/{$bill->id}/notes", [
            'notes' => 'Should not be allowed.',
        ]);

        $response->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $bill = Bill::factory()->create();

        $response = $this->putToTenant("/bills/{$bill->id}/notes", ['notes' => 'test']);

        $response->assertRedirect($this->tenantUrl('/login'));
    }

    public function test_note_validation_rejects_overly_long_text(): void
    {
        $owner = User::factory()->for($this->tenant)->create();
        $owner->assignRole('Owner');
        $bill = Bill::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($owner)->putToTenant("/bills/{$bill->id}/notes", [
            'notes' => str_repeat('a', 2001),
        ]);

        $response->assertSessionHasErrors('notes');
    }

    public function test_bill_detail_page_shows_the_note(): void
    {
        $owner = User::factory()->for($this->tenant)->create();
        $owner->assignRole('Owner');
        $bill = Bill::factory()->create(['tenant_id' => $this->tenant->id, 'notes' => 'Internal note about a client dispute.']);

        $response = $this->actingAs($owner)->getFromTenant("/bills/{$bill->id}");

        $response->assertOk();
        $response->assertSee('Internal note about a client dispute.');
    }

    public function test_printed_receipt_never_shows_the_note(): void
    {
        $owner = User::factory()->for($this->tenant)->create();
        $owner->assignRole('Owner');
        $bill = Bill::factory()->create(['tenant_id' => $this->tenant->id, 'notes' => 'Secret internal note, must not print.']);

        $response = $this->actingAs($owner)->getFromTenant("/bills/{$bill->id}/print");

        $response->assertOk();
        $response->assertDontSee('Secret internal note, must not print.');
    }
}
