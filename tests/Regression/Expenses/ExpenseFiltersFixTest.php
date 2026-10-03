<?php

namespace Tests\Regression\Expenses;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsTenant;
use Tests\TestCase;

class ExpenseFiltersFixTest extends TestCase
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

    public function test_expense_list_could_not_previously_span_more_than_one_month(): void
    {
        Expense::factory()->create(['tenant_id' => $this->tenant->id, 'description' => 'August item', 'expense_date' => '2026-08-15']);
        Expense::factory()->create(['tenant_id' => $this->tenant->id, 'description' => 'October item', 'expense_date' => '2026-10-15']);

        $response = $this->actingAs($this->owner())->getFromTenant('/expenses?from=2026-08-01&to=2026-10-31');

        $response->assertSee('August item');
        $response->assertSee('October item');
    }

    public function test_search_treats_percent_as_literal_text(): void
    {
        Expense::factory()->create(['tenant_id' => $this->tenant->id, 'description' => 'Plain tea', 'expense_date' => now()->format('Y-m-d')]);
        Expense::factory()->create(['tenant_id' => $this->tenant->id, 'description' => '50% advance', 'expense_date' => now()->format('Y-m-d')]);

        $response = $this->actingAs($this->owner())->getFromTenant('/expenses?search=%25');

        $response->assertSee('50% advance');
        $response->assertDontSee('Plain tea');
    }

    public function test_category_filter_ignores_other_categories_and_uncategorised_rows(): void
    {
        $rent = ExpenseCategory::factory()->create(['tenant_id' => $this->tenant->id]);
        $other = ExpenseCategory::factory()->create(['tenant_id' => $this->tenant->id]);
        Expense::factory()->create(['tenant_id' => $this->tenant->id, 'category_id' => $rent->id, 'description' => 'In rent', 'expense_date' => now()->format('Y-m-d')]);
        Expense::factory()->create(['tenant_id' => $this->tenant->id, 'category_id' => $other->id, 'description' => 'In other', 'expense_date' => now()->format('Y-m-d')]);
        Expense::factory()->create(['tenant_id' => $this->tenant->id, 'category_id' => null, 'description' => 'No category', 'expense_date' => now()->format('Y-m-d')]);

        $response = $this->actingAs($this->owner())->getFromTenant("/expenses?category_id={$rent->id}");

        $response->assertSee('In rent');
        $response->assertDontSee('In other');
        $response->assertDontSee('No category');
    }
}
