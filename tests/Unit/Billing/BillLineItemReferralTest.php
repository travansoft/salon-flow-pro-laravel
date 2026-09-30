<?php

namespace Tests\Unit\Billing;

use App\Models\BillLineItem;
use App\Models\StaffProfile;
use Tests\TestCase;

class BillLineItemReferralTest extends TestCase
{
    public function test_referred_by_relation_points_at_the_referral_column(): void
    {
        $relation = (new BillLineItem)->referredByStaffProfile();

        $this->assertInstanceOf(StaffProfile::class, $relation->getRelated());
        $this->assertSame('referred_by_staff_profile_id', $relation->getForeignKeyName());
    }

    public function test_referral_column_is_mass_assignable(): void
    {
        $lineItem = new BillLineItem(['referred_by_staff_profile_id' => 7]);

        $this->assertSame(7, $lineItem->referred_by_staff_profile_id);
    }
}
