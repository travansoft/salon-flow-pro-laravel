<?php

namespace Tests\Unit\Appointments;

use App\Models\Branch;
use App\Models\Client;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\StaffShift;
use App\Models\Tenant;
use App\Models\WalkIn;
use App\Services\BranchContext;
use App\Services\TenantContext;
use App\Services\WalkInService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WalkInServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_join_adds_a_walk_in_to_the_waiting_queue(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);

        $walkIn = app(WalkInService::class)->join(['name' => 'Imran', 'phone' => '9999999999']);

        $this->assertSame(WalkIn::StatusWaiting, $walkIn->status);
        $this->assertSame('Imran', $walkIn->name);
    }

    public function test_assign_without_a_service_marks_assigned_but_creates_no_appointment(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);

        $staffProfile = StaffProfile::factory()->create(['tenant_id' => $tenant->id]);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $walkIn = app(WalkInService::class)->join(['name' => 'Imran']);

        $assigned = app(WalkInService::class)->assign($walkIn, $staffProfile->id, $client->id);

        $this->assertSame(WalkIn::StatusAssigned, $assigned->status);
        $this->assertNull($assigned->appointment_id);
    }

    public function test_assign_with_a_service_creates_a_linked_appointment(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);

        $staffProfile = StaffProfile::factory()->create(['tenant_id' => $tenant->id]);
        StaffShift::factory()->create([
            'tenant_id' => $tenant->id,
            'staff_profile_id' => $staffProfile->id,
            'day_of_week' => now()->dayOfWeek,
            'start_time' => '00:00',
            'end_time' => '23:59',
            'is_working' => true,
        ]);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $service = Service::factory()->create(['tenant_id' => $tenant->id]);
        $walkIn = app(WalkInService::class)->join(['name' => 'Imran', 'service_id' => $service->id]);

        $assigned = app(WalkInService::class)->assign($walkIn, $staffProfile->id, $client->id);

        $this->assertNotNull($assigned->appointment_id);
    }
}
