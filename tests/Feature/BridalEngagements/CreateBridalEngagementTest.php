<?php

namespace Tests\Feature\BridalEngagements;

use App\Models\Bill;
use App\Models\BridalEngagement;
use App\Models\Client;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsTenant;
use Tests\TestCase;

class CreateBridalEngagementTest extends TestCase
{
    use ActsAsTenant, RefreshDatabase;

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

    /** @return array<string, mixed> */
    private function validPayload(array $overrides = []): array
    {
        return [
            'contact_number' => '9876543210',
            'bride_name' => 'Anjali Menon',
            'event_name' => 'Wedding',
            'event_date' => now()->addMonth()->toDateString(),
            'venue_type' => 'home',
            'home_location' => '12 Lake Road',
            'has_studio_trial' => '1',
            'trial_date' => now()->addWeek()->toDateString(),
            'ready_time' => '06:30',
            'total_amount' => '30000',
            'advance_amount' => '5000',
            'guest_makeup_count' => '3',
            'groom_makeup' => '1',
            'dress_type' => 'saree',
            'saree_drapist_name' => 'Latha',
            'notes' => 'Allergic to latex',
            ...$overrides,
        ];
    }

    public function test_front_desk_can_create_engagement_and_bride_becomes_a_client(): void
    {
        $response = $this->actingAs($this->frontDesk())->postToTenant('/bridal-engagements', $this->validPayload());

        $response->assertRedirect();
        $this->assertDatabaseHas('clients', ['name' => 'Anjali Menon', 'phone' => '9876543210']);
        $this->assertDatabaseHas('bridal_engagements', [
            'event_name' => 'Wedding',
            'venue_type' => 'home',
            'home_location' => '12 Lake Road',
            'total_amount' => '30000.00',
            'advance_amount' => '5000.00',
            'saree_drapist_name' => 'Latha',
        ]);
    }

    public function test_existing_client_is_reused_by_phone(): void
    {
        $client = Client::factory()->create(['tenant_id' => $this->tenant->id, 'phone' => '9876543210']);

        $this->actingAs($this->frontDesk())->postToTenant('/bridal-engagements', $this->validPayload());

        $this->assertDatabaseCount('clients', 1);
        $this->assertDatabaseHas('bridal_engagements', ['client_id' => $client->id]);
    }

    public function test_home_location_is_required_for_home_venue(): void
    {
        $response = $this->actingAs($this->frontDesk())->postToTenant('/bridal-engagements', $this->validPayload(['home_location' => '']));

        $response->assertSessionHasErrors('home_location');
    }

    public function test_trial_date_is_required_when_trial_is_yes(): void
    {
        $response = $this->actingAs($this->frontDesk())->postToTenant('/bridal-engagements', $this->validPayload(['trial_date' => '']));

        $response->assertSessionHasErrors('trial_date');
    }

    public function test_advance_cannot_exceed_total(): void
    {
        $response = $this->actingAs($this->frontDesk())->postToTenant('/bridal-engagements', $this->validPayload(['advance_amount' => '40000']));

        $response->assertSessionHasErrors('advance_amount');
    }

    public function test_required_fields_are_validated(): void
    {
        $response = $this->actingAs($this->frontDesk())->postToTenant('/bridal-engagements', []);

        $response->assertSessionHasErrors(['contact_number', 'bride_name', 'event_date', 'venue_type', 'ready_time', 'total_amount', 'dress_type']);
    }

    public function test_create_form_renders(): void
    {
        $response = $this->actingAs($this->frontDesk())->getFromTenant('/bridal-engagements/create');

        $response->assertOk()->assertSee('Contact number')->assertSee('Name of saree drapist');
    }

    public function test_front_desk_can_edit_engagement(): void
    {
        $engagement = BridalEngagement::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($this->frontDesk())->putToTenant(
            "/bridal-engagements/{$engagement->id}",
            $this->validPayload(['event_name' => 'Reception', 'client_id' => $engagement->client_id]),
        );

        $response->assertRedirect();
        $this->assertDatabaseHas('bridal_engagements', ['id' => $engagement->id, 'event_name' => 'Reception']);
    }

    public function test_create_bill_for_event_attaches_bill_and_uses_total_amount(): void
    {
        $engagement = BridalEngagement::factory()->create(['tenant_id' => $this->tenant->id, 'total_amount' => 30000]);

        $response = $this->actingAs($this->frontDesk())->postToTenant("/bridal-engagements/{$engagement->id}/bills");

        $response->assertRedirect();
        $this->assertDatabaseHas('bills', ['bridal_engagement_id' => $engagement->id, 'client_id' => $engagement->client_id, 'total' => '30000.00']);
    }

    public function test_existing_bill_can_be_attached_and_detached(): void
    {
        $engagement = BridalEngagement::factory()->create(['tenant_id' => $this->tenant->id]);
        $bill = Bill::factory()->create(['tenant_id' => $this->tenant->id]);
        $user = $this->frontDesk();

        $this->actingAs($user)->postToTenant("/bridal-engagements/{$engagement->id}/bills/attach", ['bill_id' => $bill->id]);
        $this->assertDatabaseHas('bills', ['id' => $bill->id, 'bridal_engagement_id' => $engagement->id]);

        $this->actingAs($user)->deleteFromTenant("/bridal-engagements/{$engagement->id}/bills/{$bill->id}");
        $this->assertDatabaseHas('bills', ['id' => $bill->id, 'bridal_engagement_id' => null]);
    }

    public function test_show_page_lists_attached_bills(): void
    {
        $engagement = BridalEngagement::factory()->create(['tenant_id' => $this->tenant->id]);
        $bill = Bill::factory()->create(['tenant_id' => $this->tenant->id, 'bridal_engagement_id' => $engagement->id]);

        $response = $this->actingAs($this->frontDesk())->getFromTenant("/bridal-engagements/{$engagement->id}");

        $response->assertOk()->assertSee($bill->invoiceNumber());
    }
}
