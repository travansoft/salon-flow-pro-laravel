<?php

namespace Tests\Feature\Billing;

use App\Models\Bill;
use App\Models\BillAudit;
use App\Models\BillLineItem;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsTenant;
use Tests\Concerns\CreatesIncentiveBills;
use Tests\TestCase;

class EditBillLineStaffTest extends TestCase
{
    use ActsAsTenant, CreatesIncentiveBills, RefreshDatabase;

    private User $owner;

    private Service $service;

    private StaffProfile $rizwan;

    private StaffProfile $azam;

    private Bill $bill;

    private BillLineItem $line;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenant();
        $this->seed(PermissionSeeder::class);
        $this->useTenantAndBranchContext();

        $this->owner = User::factory()->for($this->tenant)->create();
        $this->owner->assignRole('Owner');
        $this->assignToBranch($this->owner);

        $this->service = Service::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->rizwan = StaffProfile::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Rizwan Khan']);
        $this->azam = StaffProfile::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Azam Ali']);
        $this->service->staff()->attach([$this->rizwan->id, $this->azam->id]);

        $this->bill = $this->billWithLine('1000', $this->rizwan);
        $this->line = $this->bill->lineItems()->firstOrFail();
        $this->line->update(['service_id' => $this->service->id]);
    }

    /** @return array<string, mixed> */
    private function payload(?int $servicingId, ?int $referrerId = null): array
    {
        return [
            'items' => [
                $this->line->id => [
                    'staff_profile_id' => $servicingId,
                    'referred_by_staff_profile_id' => $referrerId,
                ],
            ],
        ];
    }

    public function test_edit_form_lists_each_line_with_its_current_staff(): void
    {
        $response = $this->actingAs($this->owner)->getFromTenant("/bills/{$this->bill->id}/edit");

        $response->assertOk();
        $response->assertSee('servicing staff');
        $response->assertSee('Azam Ali');
    }

    public function test_owner_can_change_the_servicing_staff_of_a_line(): void
    {
        $response = $this->actingAs($this->owner)->putToTenant("/bills/{$this->bill->id}", $this->payload($this->azam->id));

        $response->assertRedirect();
        $this->assertSame($this->azam->id, $this->line->refresh()->staff_profile_id);
    }

    public function test_owner_can_add_and_clear_the_referring_staff(): void
    {
        $this->actingAs($this->owner)->putToTenant("/bills/{$this->bill->id}", $this->payload($this->rizwan->id, $this->azam->id));
        $this->assertSame($this->azam->id, $this->line->refresh()->referred_by_staff_profile_id);

        $this->actingAs($this->owner)->putToTenant("/bills/{$this->bill->id}", $this->payload($this->rizwan->id, null));
        $this->assertNull($this->line->refresh()->referred_by_staff_profile_id);
    }

    public function test_staff_changes_are_recorded_in_the_bill_history(): void
    {
        $this->actingAs($this->owner)->putToTenant("/bills/{$this->bill->id}", $this->payload($this->azam->id, $this->rizwan->id));

        $this->assertDatabaseHas('bill_audits', [
            'bill_id' => $this->bill->id,
            'action' => BillAudit::ActionEdited,
            'field' => 'servicing_staff',
            'new_value' => "{$this->line->description}: Azam Ali",
            'changed_by' => $this->owner->id,
        ]);
        $this->assertDatabaseHas('bill_audits', [
            'bill_id' => $this->bill->id,
            'field' => 'referring_staff',
            'old_value' => "{$this->line->description}: Direct",
            'new_value' => "{$this->line->description}: Rizwan Khan",
        ]);
    }

    public function test_unchanged_staff_records_no_history(): void
    {
        $this->actingAs($this->owner)->putToTenant("/bills/{$this->bill->id}", $this->payload($this->rizwan->id));

        $this->assertDatabaseMissing('bill_audits', ['bill_id' => $this->bill->id, 'field' => 'servicing_staff']);
        $this->assertDatabaseMissing('bill_audits', ['bill_id' => $this->bill->id, 'field' => 'referring_staff']);
    }

    public function test_history_on_the_bill_page_describes_the_staff_change(): void
    {
        $this->actingAs($this->owner)->putToTenant("/bills/{$this->bill->id}", $this->payload($this->azam->id));

        $response = $this->actingAs($this->owner)->getFromTenant("/bills/{$this->bill->id}");

        $response->assertSee('Servicing staff changed');
    }

    public function test_servicing_staff_cannot_be_blank(): void
    {
        $response = $this->actingAs($this->owner)->putToTenant("/bills/{$this->bill->id}", $this->payload(null));

        $response->assertSessionHasErrors("items.{$this->line->id}.staff_profile_id");
        $this->assertSame($this->rizwan->id, $this->line->refresh()->staff_profile_id);
    }

    public function test_staff_who_cannot_perform_the_service_is_rejected(): void
    {
        $outsider = StaffProfile::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($this->owner)->putToTenant("/bills/{$this->bill->id}", $this->payload($outsider->id));

        $response->assertSessionHasErrors("items.{$this->line->id}.staff_profile_id");
    }

    public function test_staff_from_another_tenant_is_rejected(): void
    {
        $otherStaff = StaffProfile::factory()->create(['tenant_id' => Tenant::factory()->create()->id]);

        $response = $this->actingAs($this->owner)->putToTenant("/bills/{$this->bill->id}", $this->payload($this->rizwan->id, $otherStaff->id));

        $response->assertSessionHasErrors("items.{$this->line->id}.referred_by_staff_profile_id");
    }

    public function test_line_from_another_bill_is_rejected(): void
    {
        $otherBill = $this->billWithLine('500', $this->rizwan);
        $otherLine = $otherBill->lineItems()->firstOrFail();

        $response = $this->actingAs($this->owner)->putToTenant("/bills/{$this->bill->id}", [
            'items' => [$otherLine->id => ['staff_profile_id' => $this->azam->id]],
        ]);

        $response->assertSessionHasErrors('items');
        $this->assertSame($this->rizwan->id, $otherLine->refresh()->staff_profile_id);
    }

    public function test_front_desk_cannot_change_line_staff(): void
    {
        $frontDesk = User::factory()->for($this->tenant)->create();
        $frontDesk->assignRole('FrontDesk');
        $this->assignToBranch($frontDesk);

        $response = $this->actingAs($frontDesk)->putToTenant("/bills/{$this->bill->id}", $this->payload($this->azam->id));

        $response->assertForbidden();
        $this->assertSame($this->rizwan->id, $this->line->refresh()->staff_profile_id);
    }

    public function test_cancelled_bill_staff_cannot_be_changed(): void
    {
        $this->bill->update(['status' => Bill::StatusVoid]);

        $response = $this->actingAs($this->owner)->putToTenant("/bills/{$this->bill->id}", $this->payload($this->azam->id));

        $response->assertForbidden();
    }
}
