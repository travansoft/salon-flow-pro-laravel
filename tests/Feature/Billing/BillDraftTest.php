<?php

namespace Tests\Feature\Billing;

use App\Models\BillDraft;
use App\Models\Client;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsTenant;
use Tests\Concerns\CreatesEligibleStaff;
use Tests\TestCase;

class BillDraftTest extends TestCase
{
    use ActsAsTenant, CreatesEligibleStaff, RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenant();
        $this->seed(PermissionSeeder::class);

        $this->user = User::factory()->for($this->tenant)->create();
        $this->user->assignRole('FrontDesk');
    }

    /** @return array<string, mixed> */
    private function draftPayload(): array
    {
        return [
            'client_name' => 'Asha',
            'client_phone' => '9876543210',
            'discount_mode' => 'percent',
            'discount_value' => 10,
            'payment_method' => 'cash',
            'notes' => 'Bridal trial',
            'lines' => [
                [
                    'id' => 0,
                    'serviceId' => 1,
                    'description' => 'Haircut',
                    'priceInclusive' => 500,
                    'taxRate' => 18,
                    'quantity' => 2,
                    'staffProfileId' => null,
                    'isCombo' => false,
                    'components' => [],
                ],
            ],
        ];
    }

    public function test_saving_a_draft_stores_it_for_the_user_with_display_fields(): void
    {
        $response = $this->actingAs($this->user)->postToTenant('/bill-drafts', $this->draftPayload());

        $response->assertOk()->assertJsonStructure(['draft_id', 'redirect']);
        $this->assertDatabaseHas('bill_drafts', [
            'id' => $response->json('draft_id'),
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
            'client_name' => 'Asha',
            'item_count' => 1,
            'total' => 900,
        ]);
    }

    public function test_draft_can_be_saved_without_staff_or_client_details(): void
    {
        $response = $this->actingAs($this->user)->postToTenant('/bill-drafts', [
            'lines' => [['serviceId' => 5, 'description' => 'Facial', 'priceInclusive' => 800, 'quantity' => 1]],
        ]);

        $response->assertOk();
    }

    public function test_empty_draft_is_rejected(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson($this->tenantUrl('/bill-drafts'), ['lines' => []]);

        $response->assertJsonValidationErrors('lines');
        $this->assertDatabaseCount('bill_drafts', 0);
    }

    public function test_updating_a_draft_replaces_its_contents(): void
    {
        $draft = BillDraft::factory()->create(['tenant_id' => $this->tenant->id, 'user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)->putToTenant("/bill-drafts/{$draft->id}", $this->draftPayload());

        $response->assertOk();
        $this->assertDatabaseHas('bill_drafts', ['id' => $draft->id, 'client_name' => 'Asha', 'total' => 900]);
        $this->assertDatabaseCount('bill_drafts', 1);
    }

    public function test_updating_a_missing_draft_returns_not_found(): void
    {
        $response = $this->actingAs($this->user)->putToTenant('/bill-drafts/99999', $this->draftPayload());

        $response->assertNotFound();
    }

    public function test_discarding_a_draft_removes_it(): void
    {
        $draft = BillDraft::factory()->create(['tenant_id' => $this->tenant->id, 'user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)->deleteFromTenant("/bill-drafts/{$draft->id}");

        $response->assertRedirect();
        $this->assertDatabaseMissing('bill_drafts', ['id' => $draft->id]);
    }

    public function test_discarding_a_missing_draft_returns_not_found(): void
    {
        $response = $this->actingAs($this->user)->deleteFromTenant('/bill-drafts/99999');

        $response->assertNotFound();
    }

    public function test_new_bill_screen_loads_the_draft_for_continuing(): void
    {
        $draft = BillDraft::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'payload' => ['client_name' => 'Resumed Rita', 'lines' => []],
        ]);

        $response = $this->actingAs($this->user)->getFromTenant("/bills/create?draft={$draft->id}");

        $response->assertOk()->assertSee('Resumed Rita')->assertSee('Discard draft');
    }

    public function test_new_bill_screen_returns_not_found_for_unknown_draft(): void
    {
        $response = $this->actingAs($this->user)->getFromTenant('/bills/create?draft=99999');

        $response->assertNotFound();
    }

    public function test_bills_list_shows_own_drafts_above_the_list(): void
    {
        BillDraft::factory()->create(['tenant_id' => $this->tenant->id, 'user_id' => $this->user->id, 'client_name' => 'Listed Lata']);

        $response = $this->actingAs($this->user)->getFromTenant('/bills');

        $response->assertOk()->assertSee('Listed Lata')->assertSee('Draft bills');
    }

    public function test_dashboard_shows_own_drafts(): void
    {
        BillDraft::factory()->create(['tenant_id' => $this->tenant->id, 'user_id' => $this->user->id, 'client_name' => 'Dash Divya']);

        $response = $this->actingAs($this->user)->getFromTenant('/dashboard');

        $response->assertOk()->assertSee('Dash Divya');
    }

    public function test_settling_with_a_draft_id_creates_the_bill_and_removes_the_draft(): void
    {
        $service = Service::factory()->create(['tenant_id' => $this->tenant->id, 'price' => 500]);
        $client = Client::factory()->create(['tenant_id' => $this->tenant->id]);
        $draft = BillDraft::factory()->create(['tenant_id' => $this->tenant->id, 'user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)->postToTenant('/bills/settle', [
            'client_id' => $client->id,
            'items' => [
                ['service_id' => $service->id, 'staff_profile_id' => $this->eligibleStaffFor($service)->id, 'quantity' => 1],
            ],
            'payment_method' => 'cash',
            'draft_id' => $draft->id,
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('bills', ['id' => $response->json('bill_id')]);
        $this->assertDatabaseMissing('bill_drafts', ['id' => $draft->id]);
    }
}
