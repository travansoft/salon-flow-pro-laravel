<?php

namespace Tests\Feature\Reports;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsTenant;
use Tests\TestCase;

class ExpenseSummaryTest extends TestCase
{
    use ActsAsTenant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenant();
        $this->seed(PermissionSeeder::class);
    }

    private function owner(): User
    {
        $owner = User::factory()->for($this->tenant)->create();
        $owner->assignRole('Owner');

        return $owner;
    }

    public function test_owner_sees_category_totals_for_the_date_range(): void
    {
        $rent = ExpenseCategory::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Shop rent']);
        Expense::factory()->create(['tenant_id' => $this->tenant->id, 'category_id' => $rent->id, 'amount' => 1500, 'expense_date' => '2026-10-02']);
        Expense::factory()->create(['tenant_id' => $this->tenant->id, 'category_id' => $rent->id, 'amount' => 777, 'expense_date' => '2026-08-02']);

        $response = $this->actingAs($this->owner())->getFromTenant('/reports/expense-summary?from=2026-10-01&to=2026-10-31');

        $response->assertOk();
        $response->assertViewIs('admin.reports.expenseSummary');
        $response->assertSee('Shop rent');
        $response->assertSee('1,500.00');
        $response->assertDontSee('777.00');
    }

    public function test_category_row_links_to_filtered_expense_list(): void
    {
        $rent = ExpenseCategory::factory()->create(['tenant_id' => $this->tenant->id]);
        Expense::factory()->create(['tenant_id' => $this->tenant->id, 'category_id' => $rent->id, 'expense_date' => '2026-10-02']);

        $response = $this->actingAs($this->owner())->getFromTenant('/reports/expense-summary?from=2026-10-01&to=2026-10-31');

        $response->assertSee("category_id={$rent->id}", false);
        $response->assertSee('from=2026-10-01', false);
    }

    public function test_uncategorised_expenses_link_to_the_none_filter(): void
    {
        Expense::factory()->create(['tenant_id' => $this->tenant->id, 'category_id' => null, 'expense_date' => '2026-10-02']);

        $response = $this->actingAs($this->owner())->getFromTenant('/reports/expense-summary?from=2026-10-01&to=2026-10-31');

        $response->assertSee('Uncategorised');
        $response->assertSee('category_id=none', false);
    }

    public function test_empty_range_shows_empty_state(): void
    {
        $response = $this->actingAs($this->owner())->getFromTenant('/reports/expense-summary?from=2020-01-01&to=2020-01-31');

        $response->assertOk();
        $response->assertSee('No expenses in this period.');
    }

    public function test_end_date_before_start_date_is_rejected(): void
    {
        $response = $this->actingAs($this->owner())->getFromTenant('/reports/expense-summary?from=2026-10-05&to=2026-10-01');

        $response->assertSessionHasErrors('to');
    }

    public function test_owner_can_view_summary_on_the_slug_path(): void
    {
        $this->actingAs($this->owner())->get($this->bySlugUrl('/reports/expense-summary'))->assertOk();
    }

    public function test_stylist_cannot_view_expense_summary(): void
    {
        $stylist = User::factory()->for($this->tenant)->create();
        $stylist->assignRole('Stylist');

        $this->actingAs($stylist)->getFromTenant('/reports/expense-summary')->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->getFromTenant('/reports/expense-summary')->assertRedirect();
    }
}
