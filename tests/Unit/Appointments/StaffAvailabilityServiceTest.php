<?php

namespace Tests\Unit\Appointments;

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\StaffShift;
use App\Models\Tenant;
use App\Services\BranchContext;
use App\Services\StaffAvailabilityService;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffAvailabilityServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeService(): StaffAvailabilityService
    {
        return app(StaffAvailabilityService::class);
    }

    public function test_staff_is_available_within_their_working_hours_with_no_conflicts(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);

        $staffProfile = StaffProfile::factory()->create(['tenant_id' => $tenant->id]);
        $monday = now()->next(1)->setTime(10, 0);

        StaffShift::factory()->create([
            'tenant_id' => $tenant->id,
            'staff_profile_id' => $staffProfile->id,
            'day_of_week' => $monday->dayOfWeek,
            'start_time' => '09:00',
            'end_time' => '18:00',
            'is_working' => true,
        ]);

        $service = $this->makeService();

        $this->assertTrue($service->isAvailable($staffProfile, $monday, $monday->copy()->addHour()));
    }

    public function test_staff_is_unavailable_outside_their_working_hours(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);

        $staffProfile = StaffProfile::factory()->create(['tenant_id' => $tenant->id]);
        $monday = now()->next(1)->setTime(20, 0);

        StaffShift::factory()->create([
            'tenant_id' => $tenant->id,
            'staff_profile_id' => $staffProfile->id,
            'day_of_week' => $monday->dayOfWeek,
            'start_time' => '09:00',
            'end_time' => '18:00',
            'is_working' => true,
        ]);

        $service = $this->makeService();

        $this->assertFalse($service->isAvailable($staffProfile, $monday, $monday->copy()->addHour()));
    }

    public function test_staff_is_unavailable_on_an_approved_leave_override_day(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);

        $staffProfile = StaffProfile::factory()->create(['tenant_id' => $tenant->id]);
        $monday = now()->next(1)->setTime(10, 0);

        StaffShift::factory()->create([
            'tenant_id' => $tenant->id,
            'staff_profile_id' => $staffProfile->id,
            'day_of_week' => $monday->dayOfWeek,
            'start_time' => '09:00',
            'end_time' => '18:00',
            'is_working' => true,
        ]);

        StaffShift::factory()->create([
            'tenant_id' => $tenant->id,
            'staff_profile_id' => $staffProfile->id,
            'day_of_week' => null,
            'override_date' => $monday->toDateString(),
            'start_time' => '00:00',
            'end_time' => '00:00',
            'is_working' => false,
        ]);

        $service = $this->makeService();

        $this->assertFalse($service->isAvailable($staffProfile, $monday, $monday->copy()->addHour()));
    }

    public function test_staff_is_unavailable_when_an_overlapping_appointment_exists(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);

        $staffProfile = StaffProfile::factory()->create(['tenant_id' => $tenant->id]);
        $monday = now()->next(1)->setTime(10, 0);

        StaffShift::factory()->create([
            'tenant_id' => $tenant->id,
            'staff_profile_id' => $staffProfile->id,
            'day_of_week' => $monday->dayOfWeek,
            'start_time' => '09:00',
            'end_time' => '18:00',
            'is_working' => true,
        ]);

        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $service = Service::factory()->create(['tenant_id' => $tenant->id, 'duration_minutes' => 60]);
        $appointment = Appointment::factory()->create([
            'tenant_id' => $tenant->id,
            'client_id' => $client->id,
            'start_at' => $monday,
            'end_at' => $monday->copy()->addHour(),
        ]);
        $appointment->services()->attach($service, [
            'staff_profile_id' => $staffProfile->id,
            'price_at_booking' => $service->price,
            'duration_minutes_at_booking' => $service->duration_minutes,
            'start_at' => $monday,
            'end_at' => $monday->copy()->addHour(),
        ]);

        $service = $this->makeService();

        $this->assertFalse($service->isAvailable($staffProfile, $monday->copy()->addMinutes(30), $monday->copy()->addMinutes(90)));
    }
}
