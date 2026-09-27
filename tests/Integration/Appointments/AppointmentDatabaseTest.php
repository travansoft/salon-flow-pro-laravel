<?php

namespace Tests\Integration\Appointments;

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\StaffShift;
use App\Models\Tenant;
use App\Services\AppointmentService;
use App\Services\BranchContext;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppointmentDatabaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_booking_persists_appointment_services_pivot_and_reminders(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);

        $start = now()->next(1)->setTime(10, 0);
        $staffProfile = StaffProfile::factory()->create(['tenant_id' => $tenant->id]);
        StaffShift::factory()->create([
            'tenant_id' => $tenant->id,
            'staff_profile_id' => $staffProfile->id,
            'day_of_week' => $start->dayOfWeek,
            'start_time' => '09:00',
            'end_time' => '18:00',
            'is_working' => true,
        ]);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $service = Service::factory()->create(['tenant_id' => $tenant->id]);

        $appointment = app(AppointmentService::class)->book($client->id, $start, [['service_id' => $service->id, 'staff_profile_id' => $staffProfile->id]]);

        $this->assertDatabaseHas('appointment_service', ['appointment_id' => $appointment->id, 'service_id' => $service->id]);
        $this->assertSame(2, $appointment->reminders()->count());
        $this->assertDatabaseHas('appointment_reminders', ['appointment_id' => $appointment->id, 'type' => 'confirmation']);
        $this->assertDatabaseHas('appointment_reminders', ['appointment_id' => $appointment->id, 'type' => 'reminder']);
    }

    public function test_tenant_scope_excludes_appointments_from_other_tenants(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        Appointment::factory()->create(['tenant_id' => $tenantA->id]);
        Appointment::factory()->create(['tenant_id' => $tenantB->id]);

        app(TenantContext::class)->set($tenantA);
        $branchA = Branch::factory()->create(['tenant_id' => $tenantA->id]);
        app(BranchContext::class)->set($branchA);

        $this->assertSame(1, Appointment::count());
    }

    public function test_cancelling_soft_deletes_neither_appointment_nor_its_history(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);

        $appointment = Appointment::factory()->create(['tenant_id' => $tenant->id]);
        $appointment->statusHistories()->create([
            'tenant_id' => $tenant->id,
            'from_status' => 'booked',
            'to_status' => 'cancelled',
            'reason' => 'client_requested',
        ]);

        $appointment->update(['status' => 'cancelled']);

        $this->assertDatabaseHas('appointments', ['id' => $appointment->id, 'status' => 'cancelled']);
        $this->assertDatabaseHas('appointment_status_histories', ['appointment_id' => $appointment->id]);
    }
}
