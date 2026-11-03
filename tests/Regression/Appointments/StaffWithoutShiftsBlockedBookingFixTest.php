<?php

namespace Tests\Regression\Appointments;

use App\Models\Branch;
use App\Models\StaffProfile;
use App\Models\Tenant;
use App\Services\BranchContext;
use App\Services\StaffAvailabilityService;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffWithoutShiftsBlockedBookingFixTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_without_shifts_is_no_longer_reported_unavailable_by_default(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        app(BranchContext::class)->set(Branch::factory()->create(['tenant_id' => $tenant->id]));
        $staffProfile = StaffProfile::factory()->create(['tenant_id' => $tenant->id]);
        $monday = now()->next(1)->setTime(10, 0);

        $available = app(StaffAvailabilityService::class)->isAvailable($staffProfile, $monday, $monday->copy()->addHour());

        $this->assertFalse($tenant->check_staff_availability);
        $this->assertTrue($available);
    }
}
