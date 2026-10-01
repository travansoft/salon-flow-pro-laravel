<?php

namespace Tests\Feature\Reports;

use App\Models\Bill;
use App\Models\BillPayment;
use App\Models\Expense;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsTenant;
use Tests\TestCase;

class DayBookTest extends TestCase
{
    use ActsAsTenant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenant();
        $this->seed(PermissionSeeder::class);
    }

    public function test_owner_can_view_day_book_with_bill_and_expense_entries(): void
    {
        $owner = User::factory()->for($this->tenant)->create();
        $owner->assignRole('Owner');

        $bill = Bill::factory()->paid()->create(['tenant_id' => $this->tenant->id, 'total' => 800]);
        BillPayment::factory()->create(['tenant_id' => $this->tenant->id, 'bill_id' => $bill->id, 'method' => 'upi', 'amount' => 800]);
        Expense::factory()->create([
            'tenant_id' => $this->tenant->id,
            'description' => 'Tea and snacks',
            'payment_method' => 'cash',
            'amount' => 120,
            'expense_date' => now()->format('Y-m-d'),
        ]);

        $response = $this->actingAs($owner)->getFromTenant('/reports/day-book');

        $response->assertOk();
        $response->assertViewIs('admin.reports.dayBook');
        $response->assertSee('Tea and snacks');
        $response->assertSee('Closing UPI');
        $response->assertSee('800.00');
    }

    public function test_owner_can_view_day_book_on_the_slug_path(): void
    {
        $owner = User::factory()->for($this->tenant)->create();
        $owner->assignRole('Owner');

        $this->actingAs($owner)->get($this->bySlugUrl('/reports/day-book'))->assertOk();
    }

    public function test_end_date_before_start_date_is_rejected(): void
    {
        $owner = User::factory()->for($this->tenant)->create();
        $owner->assignRole('Owner');

        $response = $this->actingAs($owner)->getFromTenant('/reports/day-book?from=2026-10-05&to=2026-10-01');

        $response->assertSessionHasErrors('to');
    }

    public function test_stylist_cannot_view_day_book(): void
    {
        $stylist = User::factory()->for($this->tenant)->create();
        $stylist->assignRole('Stylist');

        $this->actingAs($stylist)->getFromTenant('/reports/day-book')->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->getFromTenant('/reports/day-book')->assertRedirect();
    }
}
