<?php

namespace Tests\Concerns;

use App\Models\Service;
use App\Models\StaffProfile;

/** Requires ActsAsTenant. */
trait CreatesEligibleStaff
{
    protected function eligibleStaffFor(Service $service): StaffProfile
    {
        $staff = StaffProfile::factory()->create(['tenant_id' => $this->tenant->id]);
        $service->staff()->attach($staff->id);

        return $staff;
    }
}
