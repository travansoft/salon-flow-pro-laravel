<?php

namespace Tests\Regression\Billing;

use App\Models\Bill;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsTenant;
use Tests\TestCase;

class BillNoteNeverAppearsOnPrintedReceiptFixTest extends TestCase
{
    use ActsAsTenant, RefreshDatabase;

    /**
     * Internal bill notes are meant only for staff to see inside the admin
     * panel (e.g. a dispute or a verbally-approved discount) and must never
     * leak onto the customer-facing printed receipt, which a client could
     * read at the counter.
     */
    public function test_internal_note_is_visible_in_the_system_but_absent_from_the_print_view(): void
    {
        $this->setUpTenant();
        $this->seed(PermissionSeeder::class);

        $owner = User::factory()->for($this->tenant)->create();
        $owner->assignRole('Owner');
        $bill = Bill::factory()->create([
            'tenant_id' => $this->tenant->id,
            'notes' => 'CONFIDENTIAL: client paid cash off the books, do not repeat.',
        ]);

        $systemView = $this->actingAs($owner)->getFromTenant("/bills/{$bill->id}");
        $printView = $this->actingAs($owner)->getFromTenant("/bills/{$bill->id}/print");

        $systemView->assertOk()->assertSee('CONFIDENTIAL: client paid cash off the books, do not repeat.');
        $printView->assertOk()->assertDontSee('CONFIDENTIAL: client paid cash off the books, do not repeat.');
    }
}
