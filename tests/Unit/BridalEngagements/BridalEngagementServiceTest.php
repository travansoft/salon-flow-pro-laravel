<?php

namespace Tests\Unit\BridalEngagements;

use App\Models\Branch;
use App\Models\BridalEngagement;
use App\Models\Client;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\StaffShift;
use App\Models\Tenant;
use App\Services\BranchContext;
use App\Services\BridalEngagementService;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BridalEngagementServiceTest extends TestCase
{
    use RefreshDatabase;

    private function workingStaff(Tenant $tenant, Carbon $at): StaffProfile
    {
        $staffProfile = StaffProfile::factory()->create(['tenant_id' => $tenant->id]);

        StaffShift::factory()->create([
            'tenant_id' => $tenant->id,
            'staff_profile_id' => $staffProfile->id,
            'day_of_week' => $at->dayOfWeek,
            'start_time' => '09:00',
            'end_time' => '20:00',
            'is_working' => true,
        ]);

        return $staffProfile;
    }

    public function test_create_engagement_links_trial_and_event_day_appointments_together(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);

        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $trialStart = now()->next(1)->setTime(10, 0);
        $eventStart = now()->next(1)->addWeeks(2)->setTime(9, 0);
        $trialStaff = $this->workingStaff($tenant, $trialStart);
        $eventStaff = $this->workingStaff($tenant, $eventStart);
        $service = Service::factory()->create(['tenant_id' => $tenant->id]);

        $engagement = app(BridalEngagementService::class)->createEngagement(
            clientId: $client->id,
            eventDate: $eventStart,
            venue: 'The Grand Ballroom',
            trialStaffProfileId: $trialStaff->id,
            trialStartAt: $trialStart,
            trialLineItems: [['service_id' => $service->id]],
            eventStaffProfileId: $eventStaff->id,
            eventStartAt: $eventStart,
            eventLineItems: [['service_id' => $service->id]],
        );

        $this->assertSame(2, $engagement->appointments()->count());
        $this->assertNotNull($engagement->trialAppointment());
        $this->assertNotNull($engagement->eventDayAppointment());
        $this->assertSame(BridalEngagement::RoleTrial, $engagement->trialAppointment()->engagement_role);
        $this->assertSame(BridalEngagement::RoleEventDay, $engagement->eventDayAppointment()->engagement_role);
    }

    public function test_create_engagement_marks_event_day_as_on_location_by_default(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);

        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $trialStart = now()->next(1)->setTime(10, 0);
        $eventStart = now()->next(1)->addWeeks(2)->setTime(9, 0);
        $trialStaff = $this->workingStaff($tenant, $trialStart);
        $eventStaff = $this->workingStaff($tenant, $eventStart);
        $service = Service::factory()->create(['tenant_id' => $tenant->id]);

        $engagement = app(BridalEngagementService::class)->createEngagement(
            clientId: $client->id,
            eventDate: $eventStart,
            venue: 'The Grand Ballroom',
            trialStaffProfileId: $trialStaff->id,
            trialStartAt: $trialStart,
            trialLineItems: [['service_id' => $service->id]],
            eventStaffProfileId: $eventStaff->id,
            eventStartAt: $eventStart,
            eventLineItems: [['service_id' => $service->id]],
        );

        $this->assertTrue($engagement->eventDayAppointment()->is_on_location);
        $this->assertSame('The Grand Ballroom', $engagement->eventDayAppointment()->venue_address);
        $this->assertFalse($engagement->trialAppointment()->is_on_location);
    }

    public function test_create_engagement_assigns_traveling_staff(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);

        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $trialStart = now()->next(1)->setTime(10, 0);
        $eventStart = now()->next(1)->addWeeks(2)->setTime(9, 0);
        $trialStaff = $this->workingStaff($tenant, $trialStart);
        $eventStaff = $this->workingStaff($tenant, $eventStart);
        $travelingStaff = StaffProfile::factory()->create(['tenant_id' => $tenant->id]);
        $service = Service::factory()->create(['tenant_id' => $tenant->id]);

        $engagement = app(BridalEngagementService::class)->createEngagement(
            clientId: $client->id,
            eventDate: $eventStart,
            venue: 'The Grand Ballroom',
            trialStaffProfileId: $trialStaff->id,
            trialStartAt: $trialStart,
            trialLineItems: [['service_id' => $service->id]],
            eventStaffProfileId: $eventStaff->id,
            eventStartAt: $eventStart,
            eventLineItems: [['service_id' => $service->id]],
            travelingStaffProfileIds: [$travelingStaff->id],
        );

        $this->assertTrue($engagement->travelingStaff->contains($travelingStaff));
    }
}
