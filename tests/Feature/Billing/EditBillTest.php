<?php

namespace Tests\Feature\Billing;

use App\Models\Bill;
use App\Models\Client;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsTenant;
use Tests\TestCase;

class EditBillTest extends TestCase
{
    use ActsAsTenant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenant();
        $this->seed(PermissionSeeder::class);
    }

    public function test_owner_can_view_the_edit_bill_form(): void
    {
        $owner = User::factory()->for($this->tenant)->create();
        $owner->assignRole('Owner');
        $bill = Bill::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($owner)->getFromTenant("/bills/{$bill->id}/edit");

        $response->assertOk();
    }

    public function test_owner_can_switch_a_walk_in_bill_to_a_named_gst_client(): void
    {
        $owner = User::factory()->for($this->tenant)->create();
        $owner->assignRole('Owner');
        $bill = Bill::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($owner)->putToTenant("/bills/{$bill->id}", [
            'client_name' => 'Acme Traders',
            'client_gst_number' => '32AAAAA0000A1Z5',
            'notes' => 'Requested GST invoice',
        ]);

        $response->assertRedirect($this->tenantUrl("/bills/{$bill->id}"));
        $bill->refresh();
        $this->assertSame('Acme Traders', $bill->client->name);
        $this->assertSame('32AAAAA0000A1Z5', $bill->client->gst_number);
        $this->assertSame('Requested GST invoice', $bill->notes);
    }

    public function test_owner_can_reassign_a_bill_to_an_existing_client(): void
    {
        $owner = User::factory()->for($this->tenant)->create();
        $owner->assignRole('Owner');
        $bill = Bill::factory()->create(['tenant_id' => $this->tenant->id]);
        $existingClient = Client::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($owner)->putToTenant("/bills/{$bill->id}", [
            'client_id' => $existingClient->id,
        ]);

        $response->assertRedirect($this->tenantUrl("/bills/{$bill->id}"));
        $this->assertSame($existingClient->id, $bill->refresh()->client_id);
    }

    public function test_manager_cannot_edit_a_bill(): void
    {
        $manager = User::factory()->for($this->tenant)->create();
        $manager->assignRole('Manager');
        $bill = Bill::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($manager)->putToTenant("/bills/{$bill->id}", [
            'client_name' => 'Acme Traders',
        ]);

        $response->assertForbidden();
    }

    public function test_front_desk_cannot_edit_a_bill(): void
    {
        $frontDesk = User::factory()->for($this->tenant)->create();
        $frontDesk->assignRole('FrontDesk');
        $bill = Bill::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($frontDesk)->getFromTenant("/bills/{$bill->id}/edit");

        $response->assertForbidden();
    }

    public function test_a_cancelled_bill_cannot_be_edited(): void
    {
        $owner = User::factory()->for($this->tenant)->create();
        $owner->assignRole('Owner');
        $bill = Bill::factory()->create(['tenant_id' => $this->tenant->id, 'status' => Bill::StatusVoid]);

        $response = $this->actingAs($owner)->getFromTenant("/bills/{$bill->id}/edit");

        $response->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $bill = Bill::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->getFromTenant("/bills/{$bill->id}/edit");

        $response->assertRedirect($this->tenantUrl('/login'));
    }
}
