<?php

namespace Tests\Integration\Billing;

use App\Models\BillLineItem;
use App\Models\StaffProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillReferralDatabaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_deleting_the_referrer_nulls_the_reference_and_keeps_the_line_item(): void
    {
        $lineItem = BillLineItem::factory()->create();
        $referrer = StaffProfile::factory()->create(['tenant_id' => $lineItem->tenant_id]);
        $lineItem->update(['referred_by_staff_profile_id' => $referrer->id]);

        $referrer->forceDelete();

        $this->assertDatabaseHas('bill_line_items', ['id' => $lineItem->id, 'referred_by_staff_profile_id' => null]);
    }
}
