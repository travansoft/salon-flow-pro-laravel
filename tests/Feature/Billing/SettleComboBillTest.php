<?php

namespace Tests\Feature\Billing;

use App\Models\BillLineItem;
use App\Models\StaffProfile;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsTenant;
use Tests\Concerns\CreatesCombos;
use Tests\TestCase;

class SettleComboBillTest extends TestCase
{
    use ActsAsTenant, CreatesCombos, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenant();
        $this->seed(PermissionSeeder::class);
    }

    private function frontDesk(): User
    {
        $user = User::factory()->for($this->tenant)->create();
        $user->assignRole('FrontDesk');

        return $user;
    }

    public function test_settling_a_combo_creates_a_line_per_component_with_its_own_staff(): void
    {
        $combo = $this->comboWith([500, 300]);
        $components = $this->eligibleComponentStaff($combo);

        $response = $this->actingAs($this->frontDesk())->postToTenant('/bills/settle', [
            'items' => [['service_id' => $combo->id, 'components' => $components]],
            'payment_method' => 'cash',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('bills', ['id' => $response->json('bill_id'), 'status' => 'paid', 'total' => 800]);
        $this->assertDatabaseHas('bill_line_items', [
            'bill_id' => $response->json('bill_id'),
            'combo_service_id' => $combo->id,
            'staff_profile_id' => $components[0]['staff_profile_id'],
        ]);
        $this->assertDatabaseHas('bill_line_items', ['bill_id' => $response->json('bill_id'), 'staff_profile_id' => $components[1]['staff_profile_id']]);
    }

    public function test_referrer_applies_to_every_line_of_the_combo(): void
    {
        $combo = $this->comboWith([500, 300]);
        $referrer = StaffProfile::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($this->frontDesk())->postToTenant('/bills/settle', [
            'items' => [[
                'service_id' => $combo->id,
                'components' => $this->eligibleComponentStaff($combo),
                'referred_by_staff_profile_id' => $referrer->id,
            ]],
            'payment_method' => 'upi',
        ]);

        $response->assertOk();
        $this->assertSame(2, BillLineItem::query()->where('bill_id', $response->json('bill_id'))->where('referred_by_staff_profile_id', $referrer->id)->count());
    }

    public function test_combo_price_override_sets_the_bill_total(): void
    {
        $combo = $this->comboWith([500, 300]);

        $response = $this->actingAs($this->frontDesk())->postToTenant('/bills/settle', [
            'items' => [['service_id' => $combo->id, 'components' => $this->eligibleComponentStaff($combo), 'unit_price' => 700]],
            'payment_method' => 'cash',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('bills', ['id' => $response->json('bill_id'), 'total' => 700]);
    }

    public function test_staff_is_required_for_every_component(): void
    {
        $combo = $this->comboWith([500, 300]);
        $components = $this->eligibleComponentStaff($combo);
        unset($components[1]['staff_profile_id']);

        $response = $this->withHeader('Accept', 'application/json')->actingAs($this->frontDesk())->postToTenant('/bills/settle', [
            'items' => [['service_id' => $combo->id, 'components' => $components]],
            'payment_method' => 'cash',
        ]);

        $response->assertUnprocessable();
        $this->assertDatabaseCount('bills', 0);
    }

    public function test_omitting_a_component_is_rejected(): void
    {
        $combo = $this->comboWith([500, 300]);
        $components = $this->eligibleComponentStaff($combo);

        $response = $this->actingAs($this->frontDesk())->postToTenant('/bills/settle', [
            'items' => [['service_id' => $combo->id, 'components' => [$components[0]]]],
            'payment_method' => 'cash',
        ]);

        $response->assertUnprocessable();
        $this->assertDatabaseCount('bills', 0);
    }

    public function test_staff_not_eligible_for_a_component_is_rejected(): void
    {
        $combo = $this->comboWith([500, 300]);
        $components = $this->eligibleComponentStaff($combo);
        $components[0]['staff_profile_id'] = StaffProfile::factory()->create(['tenant_id' => $this->tenant->id])->id;

        $response = $this->actingAs($this->frontDesk())->postToTenant('/bills/settle', [
            'items' => [['service_id' => $combo->id, 'components' => $components]],
            'payment_method' => 'cash',
        ]);

        $response->assertUnprocessable();
        $this->assertDatabaseCount('bills', 0);
    }

    public function test_stylist_cannot_settle_a_combo(): void
    {
        $combo = $this->comboWith([500, 300]);
        $stylist = User::factory()->for($this->tenant)->create();
        $stylist->assignRole('Stylist');

        $response = $this->actingAs($stylist)->postToTenant('/bills/settle', [
            'items' => [['service_id' => $combo->id, 'components' => $this->eligibleComponentStaff($combo)]],
            'payment_method' => 'cash',
        ]);

        $response->assertForbidden();
    }

    public function test_guest_cannot_settle_a_combo(): void
    {
        $response = $this->postToTenant('/bills/settle', ['items' => [], 'payment_method' => 'cash']);

        $response->assertRedirect($this->tenantUrl('/login'));
    }
}
