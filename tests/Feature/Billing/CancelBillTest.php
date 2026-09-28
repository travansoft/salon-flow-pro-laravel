<?php

namespace Tests\Feature\Billing;

use App\Models\Bill;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsTenant;
use Tests\TestCase;

class CancelBillTest extends TestCase
{
    use ActsAsTenant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenant();
        $this->seed(PermissionSeeder::class);
    }

    public function test_owner_can_cancel_a_bill(): void
    {
        $owner = User::factory()->for($this->tenant)->create();
        $owner->assignRole('Owner');
        $bill = Bill::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($owner)->putToTenant("/bills/{$bill->id}/cancel", []);

        $response->assertRedirect($this->tenantUrl("/bills/{$bill->id}"));
        $this->assertSame(Bill::StatusVoid, $bill->refresh()->status);
    }

    public function test_manager_cannot_cancel_a_bill(): void
    {
        $manager = User::factory()->for($this->tenant)->create();
        $manager->assignRole('Manager');
        $bill = Bill::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($manager)->putToTenant("/bills/{$bill->id}/cancel", []);

        $response->assertForbidden();
        $this->assertSame(Bill::StatusUnpaid, $bill->refresh()->status);
    }

    public function test_front_desk_cannot_cancel_a_bill(): void
    {
        $frontDesk = User::factory()->for($this->tenant)->create();
        $frontDesk->assignRole('FrontDesk');
        $bill = Bill::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($frontDesk)->putToTenant("/bills/{$bill->id}/cancel", []);

        $response->assertForbidden();
    }

    public function test_a_bill_cannot_be_cancelled_twice(): void
    {
        $owner = User::factory()->for($this->tenant)->create();
        $owner->assignRole('Owner');
        $bill = Bill::factory()->create(['tenant_id' => $this->tenant->id, 'status' => Bill::StatusVoid]);

        $response = $this->actingAs($owner)->putToTenant("/bills/{$bill->id}/cancel", []);

        $response->assertForbidden();
    }

    public function test_cancelling_a_paid_bill_is_still_allowed(): void
    {
        $owner = User::factory()->for($this->tenant)->create();
        $owner->assignRole('Owner');
        $bill = Bill::factory()->paid()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($owner)->putToTenant("/bills/{$bill->id}/cancel", []);

        $response->assertRedirect($this->tenantUrl("/bills/{$bill->id}"));
        $this->assertSame(Bill::StatusVoid, $bill->refresh()->status);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $bill = Bill::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->putToTenant("/bills/{$bill->id}/cancel", []);

        $response->assertRedirect($this->tenantUrl('/login'));
    }
}
