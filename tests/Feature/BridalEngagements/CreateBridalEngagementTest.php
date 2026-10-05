<?php

namespace Tests\Feature\BridalEngagements;

use App\Models\Bill;
use App\Models\BridalEngagement;
use App\Models\Client;
use App\Models\StaffProfile;
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

    public function test_create_bill_for_event_makes_a_paid_bill_with_staff_target_credits(): void
    {
        $engagement = BridalEngagement::factory()->create(['tenant_id' => $this->tenant->id]);
        $staffA = StaffProfile::factory()->create(['tenant_id' => $this->tenant->id]);
        $staffB = StaffProfile::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($this->frontDesk())->postToTenant("/bridal-engagements/{$engagement->id}/bills", [
            'bill_date' => now()->subDays(3)->toDateString(),
            'amount' => '30000',
            'payment_method' => 'upi',
            'staff' => [
                ['staff_profile_id' => $staffA->id, 'amount' => '20000'],
                ['staff_profile_id' => $staffB->id, 'amount' => '5000'],
            ],
        ]);

        $response->assertRedirect();
        $bill = Bill::where('bridal_engagement_id', $engagement->id)->firstOrFail();
        $this->assertSame(Bill::StatusPaid, $bill->status);
        $this->assertSame('30000.00', (string) $bill->total);
        $this->assertSame($engagement->client_id, $bill->client_id);
        $this->assertTrue($bill->created_at->isSameDay(now()->subDays(3)));
        $this->assertDatabaseHas('bill_payments', ['bill_id' => $bill->id, 'method' => 'upi', 'amount' => '30000.00']);
        $this->assertTrue($bill->payments()->first()->created_at->isSameDay(now()->subDays(3)));
        $this->assertDatabaseHas('bill_line_items', ['bill_id' => $bill->id, 'staff_profile_id' => $staffA->id, 'target_amount' => '20000.00']);
        $this->assertDatabaseHas('bill_line_items', ['bill_id' => $bill->id, 'staff_profile_id' => $staffB->id, 'target_amount' => '5000.00']);
    }

    public function test_create_bill_without_staff_makes_a_single_staffless_paid_bill(): void
    {
        $engagement = BridalEngagement::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($this->frontDesk())->postToTenant("/bridal-engagements/{$engagement->id}/bills", [
            'bill_date' => now()->toDateString(),
            'amount' => '12000',
            'payment_method' => 'cash',
            'staff' => [['staff_profile_id' => '', 'amount' => '']],
        ]);

        $response->assertRedirect();
        $bill = Bill::where('bridal_engagement_id', $engagement->id)->firstOrFail();
        $this->assertSame(Bill::StatusPaid, $bill->status);
        $this->assertDatabaseCount('bill_line_items', 1);
        $this->assertDatabaseHas('bill_line_items', ['bill_id' => $bill->id, 'staff_profile_id' => null, 'target_amount' => null]);
    }

    public function test_create_bill_validates_date_amount_and_payment_mode(): void
    {
        $engagement = BridalEngagement::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($this->frontDesk())->postToTenant("/bridal-engagements/{$engagement->id}/bills", [
            'bill_date' => now()->addDay()->toDateString(),
            'amount' => '0',
            'payment_method' => 'cheque',
        ]);

        $response->assertSessionHasErrors(['bill_date', 'amount', 'payment_method']);
        $this->assertDatabaseCount('bills', 0);
    }

    public function test_create_bill_rejects_the_same_staff_twice(): void
    {
        $engagement = BridalEngagement::factory()->create(['tenant_id' => $this->tenant->id]);
        $staff = StaffProfile::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($this->frontDesk())->postToTenant("/bridal-engagements/{$engagement->id}/bills", [
            'bill_date' => now()->toDateString(),
            'amount' => '1000',
            'payment_method' => 'cash',
            'staff' => [
                ['staff_profile_id' => $staff->id, 'amount' => '500'],
                ['staff_profile_id' => $staff->id, 'amount' => '500'],
            ],
        ]);

        $response->assertSessionHasErrors('staff.1.staff_profile_id');
    }

    public function test_bill_lookup_returns_bill_info_by_number_and_invoice_number(): void
    {
        $engagement = BridalEngagement::factory()->create(['tenant_id' => $this->tenant->id]);
        $bill = Bill::factory()->create(['tenant_id' => $this->tenant->id, 'bill_number' => 42, 'financial_year' => '2026-27']);
        $user = $this->frontDesk();

        $byNumber = $this->actingAs($user)->getFromTenant("/bridal-engagements/{$engagement->id}/bills/lookup?number=42");
        $byInvoice = $this->actingAs($user)->getFromTenant("/bridal-engagements/{$engagement->id}/bills/lookup?number=".urlencode($bill->fresh()->invoiceNumber()));

        $byNumber->assertOk()->assertJsonPath('bill.id', $bill->id)->assertJsonPath('bill.unavailable_reason', null);
        $byInvoice->assertOk()->assertJsonPath('bill.id', $bill->id);
    }

    public function test_bill_lookup_flags_bills_already_attached_and_unknown_numbers(): void
    {
        $engagement = BridalEngagement::factory()->create(['tenant_id' => $this->tenant->id]);
        $other = BridalEngagement::factory()->create(['tenant_id' => $this->tenant->id]);
        Bill::factory()->create(['tenant_id' => $this->tenant->id, 'bill_number' => 7, 'financial_year' => '2026-27', 'bridal_engagement_id' => $other->id]);
        $user = $this->frontDesk();

        $taken = $this->actingAs($user)->getFromTenant("/bridal-engagements/{$engagement->id}/bills/lookup?number=7");
        $missing = $this->actingAs($user)->getFromTenant("/bridal-engagements/{$engagement->id}/bills/lookup?number=999");

        $taken->assertOk()->assertJsonPath('bill.unavailable_reason', 'This bill is already attached to another event.');
        $missing->assertNotFound();
    }

    public function test_show_page_has_modal_buttons_for_bills(): void
    {
        $engagement = BridalEngagement::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($this->frontDesk())->getFromTenant("/bridal-engagements/{$engagement->id}");

        $response->assertOk()->assertSee('createEventBillModal')->assertSee('attachEventBillModal');
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

    public function test_index_shows_billed_amount_excluding_void_bills(): void
    {
        $engagement = BridalEngagement::factory()->create(['tenant_id' => $this->tenant->id]);
        Bill::factory()->create(['tenant_id' => $this->tenant->id, 'bridal_engagement_id' => $engagement->id, 'total' => 4000]);
        Bill::factory()->create(['tenant_id' => $this->tenant->id, 'bridal_engagement_id' => $engagement->id, 'total' => 9999, 'status' => Bill::StatusVoid]);

        $response = $this->actingAs($this->frontDesk())->getFromTenant('/bridal-engagements');

        $response->assertOk()->assertSee('Billed 4,000.00');
    }

    public function test_ready_time_is_shown_with_am_pm_on_index_and_show(): void
    {
        $engagement = BridalEngagement::factory()->create(['tenant_id' => $this->tenant->id, 'ready_time' => '14:30']);
        $user = $this->frontDesk();

        $this->actingAs($user)->getFromTenant('/bridal-engagements')->assertSee('02:30 PM');
        $this->actingAs($user)->getFromTenant("/bridal-engagements/{$engagement->id}")->assertSee('02:30 PM');
    }

    public function test_show_page_lists_attached_bills(): void
    {
        $engagement = BridalEngagement::factory()->create(['tenant_id' => $this->tenant->id]);
        $bill = Bill::factory()->create(['tenant_id' => $this->tenant->id, 'bridal_engagement_id' => $engagement->id]);

        $response = $this->actingAs($this->frontDesk())->getFromTenant("/bridal-engagements/{$engagement->id}");

        $response->assertOk()->assertSee($bill->invoiceNumber());
    }
}
