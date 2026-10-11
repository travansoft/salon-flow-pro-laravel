<?php

namespace Tests\Feature\Expenses;

use App\Models\Expense;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsTenant;
use Tests\TestCase;

class ExpensePartyGstTest extends TestCase
{
    use ActsAsTenant, RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenant();
        $this->seed(PermissionSeeder::class);
        $this->owner = User::factory()->for($this->tenant)->create();
        $this->owner->assignRole('Owner');
    }

    public function test_owner_can_record_expense_with_party_and_gst(): void
    {
        $response = $this->actingAs($this->owner)->postToTenant('/expenses', [
            'description' => 'Salon chairs',
            'party_name' => 'Furniture Hub',
            'gst_number' => '29abcde1234f1z5',
            'amount' => 2000,
            'gst_amount' => 360,
            'expense_date' => '2026-10-01',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('expenses', [
            'party_name' => 'Furniture Hub',
            'gst_number' => '29ABCDE1234F1Z5',
            'base_amount' => 2000,
            'gst_amount' => 360,
            'amount' => 2360,
        ]);
    }

    public function test_invalid_gst_number_is_rejected(): void
    {
        $response = $this->actingAs($this->owner)->postToTenant('/expenses', [
            'description' => 'Salon chairs',
            'gst_number' => 'NOT-A-GSTIN',
            'amount' => 2000,
            'expense_date' => '2026-10-01',
        ]);

        $response->assertSessionHasErrors('gst_number');
    }

    public function test_negative_gst_amount_is_rejected(): void
    {
        $response = $this->actingAs($this->owner)->postToTenant('/expenses', [
            'description' => 'Salon chairs',
            'amount' => 2000,
            'gst_amount' => -5,
            'expense_date' => '2026-10-01',
        ]);

        $response->assertSessionHasErrors('gst_amount');
    }

    public function test_list_shows_party_gst_number_and_split_amounts(): void
    {
        Expense::factory()->create([
            'tenant_id' => $this->tenant->id,
            'description' => 'Shampoo stock',
            'party_name' => 'Beauty Wholesale',
            'gst_number' => '29ABCDE1234F1Z5',
            'base_amount' => 1000,
            'gst_amount' => 180,
            'amount' => 1180,
            'expense_date' => now()->toDateString(),
        ]);

        $response = $this->actingAs($this->owner)->getFromTenant('/expenses');

        $response->assertSee('Beauty Wholesale');
        $response->assertSee('29ABCDE1234F1Z5');
        $response->assertSee('1,180.00');
    }

    public function test_search_matches_party_name(): void
    {
        Expense::factory()->create([
            'tenant_id' => $this->tenant->id,
            'description' => 'Restock',
            'party_name' => 'Zenith Traders',
            'expense_date' => now()->toDateString(),
        ]);

        $response = $this->actingAs($this->owner)->getFromTenant('/expenses?search=zenith');

        $response->assertSee('Zenith Traders');
    }

    public function test_owner_can_export_expenses_to_excel(): void
    {
        Expense::factory()->create([
            'tenant_id' => $this->tenant->id,
            'expense_date' => now()->toDateString(),
        ]);

        $response = $this->actingAs($this->owner)->getFromTenant('/expenses/export');

        $response->assertOk();
        $response->assertDownload();
    }

    public function test_user_without_permission_cannot_export_expenses(): void
    {
        $stylist = User::factory()->for($this->tenant)->create();

        $response = $this->actingAs($stylist)->getFromTenant('/expenses/export');

        $response->assertForbidden();
    }

    public function test_guest_cannot_export_expenses(): void
    {
        $response = $this->getFromTenant('/expenses/export');

        $response->assertRedirect();
    }
}
