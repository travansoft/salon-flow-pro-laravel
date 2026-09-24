<?php

namespace Tests\Regression\BridalEngagements;

use App\Exceptions\StaffUnavailableException;
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
use Tests\TestCase;

class EngagementRollsBackOnEventBookingFailureFixTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Bug risk: createEngagement() books the trial appointment first, then
     * the event-day appointment. If the event-day staff member turns out to
     * be unavailable, a naive implementation could leave a dangling
     * engagement row and trial appointment behind with no event-day half,
     * violating "trial and event day are booked as one linked engagement."
     * The whole method must run inside a single DB transaction so a failed
     * event booking rolls back the trial appointment and the engagement
     * record too, leaving no partial engagement in the database.
     */
    public function test_a_failed_event_day_booking_leaves_no_partial_engagement_behind(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);

        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $trialStart = now()->next(1)->setTime(10, 0);
        $eventStart = now()->next(1)->addWeeks(2)->setTime(22, 0);

        $trialStaff = StaffProfile::factory()->create(['tenant_id' => $tenant->id]);
        StaffShift::factory()->create([
            'tenant_id' => $tenant->id,
            'staff_profile_id' => $trialStaff->id,
            'day_of_week' => $trialStart->dayOfWeek,
            'start_time' => '09:00',
            'end_time' => '20:00',
            'is_working' => true,
        ]);

        $eventStaff = StaffProfile::factory()->create(['tenant_id' => $tenant->id]);
        StaffShift::factory()->create([
            'tenant_id' => $tenant->id,
            'staff_profile_id' => $eventStaff->id,
            'day_of_week' => $eventStart->dayOfWeek,
            'start_time' => '09:00',
            'end_time' => '18:00',
            'is_working' => true,
        ]);

        $service = Service::factory()->create(['tenant_id' => $tenant->id]);

        try {
            app(BridalEngagementService::class)->createEngagement(
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

            $this->fail('Expected StaffUnavailableException was not thrown.');
        } catch (StaffUnavailableException) {
            // expected
        }

        $this->assertSame(0, BridalEngagement::count());
        $this->assertDatabaseCount('appointments', 0);
    }
}
