<?php

namespace Tests\Feature\Billing;

use App\Models\Bill;
use App\Models\BillLineItem;
use App\Models\Client;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsTenant;
use Tests\Concerns\CreatesEligibleStaff;
use Tests\TestCase;

class BillReferralStaffTest extends TestCase
{
    use ActsAsTenant, CreatesEligibleStaff, RefreshDatabase;

    private User $frontDesk;

    private Client $client;

    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenant();
        $this->seed(PermissionSeeder::class);

        $this->frontDesk = User::factory()->for($this->tenant)->create();
        $this->frontDesk->assignRole('FrontDesk');
        $this->client = Client::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->service = Service::factory()->create(['tenant_id' => $this->tenant->id, 'price' => 500]);
    }

    public function test_settled_bill_records_the_referring_staff_member(): void
    {
        $referrer = StaffProfile::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($this->frontDesk)->postJson($this->tenantUrl('/bills/settle'), $this->payload($referrer->id));

        $response->assertOk();
        $this->assertDatabaseHas('bill_line_items', [
            'service_id' => $this->service->id,
            'referred_by_staff_profile_id' => $referrer->id,
        ]);
    }

    public function test_settled_bill_defaults_to_direct_when_no_referrer_is_chosen(): void
    {
        $response = $this->actingAs($this->frontDesk)->postJson($this->tenantUrl('/bills/settle'), $this->payload(null));

        $response->assertOk();
        $this->assertDatabaseHas('bill_line_items', [
            'service_id' => $this->service->id,
            'referred_by_staff_profile_id' => null,
        ]);
    }

    public function test_settle_rejects_a_referrer_from_another_tenant(): void
    {
        $foreignReferrer = StaffProfile::factory()->create();

        $response = $this->actingAs($this->frontDesk)->postJson($this->tenantUrl('/bills/settle'), $this->payload($foreignReferrer->id));

        $response->assertJsonValidationErrors('items.0.referred_by_staff_profile_id');
    }

    public function test_bill_page_shows_the_referrer(): void
    {
        $referrer = StaffProfile::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Canvasser Asha']);
        $bill = Bill::factory()->create(['tenant_id' => $this->tenant->id, 'client_id' => $this->client->id]);
        BillLineItem::factory()->create([
            'tenant_id' => $this->tenant->id,
            'bill_id' => $bill->id,
            'referred_by_staff_profile_id' => $referrer->id,
        ]);

        $response = $this->actingAs($this->frontDesk)->get($this->tenantUrl("/bills/{$bill->id}"));

        $response->assertOk();
        $response->assertSee('Referred by: Canvasser Asha', false);
    }

    public function test_bill_page_shows_direct_when_no_referrer(): void
    {
        $bill = Bill::factory()->create(['tenant_id' => $this->tenant->id, 'client_id' => $this->client->id]);
        BillLineItem::factory()->create(['tenant_id' => $this->tenant->id, 'bill_id' => $bill->id]);

        $response = $this->actingAs($this->frontDesk)->get($this->tenantUrl("/bills/{$bill->id}"));

        $response->assertSee('Referred by: Direct', false);
    }

    /** @return array<string, mixed> */
    private function payload(?int $referrerId): array
    {
        return [
            'client_id' => $this->client->id,
            'items' => [
                [
                    'description' => $this->service->name,
                    'service_id' => $this->service->id,
                    'staff_profile_id' => $this->eligibleStaffFor($this->service)->id,
                    'referred_by_staff_profile_id' => $referrerId,
                    'unit_price' => 500,
                ],
            ],
            'payment_method' => 'cash',
        ];
    }
}
