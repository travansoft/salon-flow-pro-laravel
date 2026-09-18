<?php

namespace Tests\Feature\Billing;

use App\Models\Appointment;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsTenant;
use Tests\TestCase;

class GenerateBillFromAppointmentTest extends TestCase
{
    use ActsAsTenant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenant();
        $this->seed(PermissionSeeder::class);
    }

    public function test_front_desk_can_generate_a_bill_from_a_completed_appointment(): void
    {
        $frontDesk = User::factory()->for($this->tenant)->create();
        $frontDesk->assignRole('FrontDesk');

        $appointment = Appointment::factory()->create(['tenant_id' => $this->tenant->id, 'status' => 'completed']);
        $staffProfile = StaffProfile::factory()->create(['tenant_id' => $this->tenant->id]);
        $service = Service::factory()->create(['tenant_id' => $this->tenant->id, 'price' => 500]);
        $appointment->services()->attach($service, [
            'staff_profile_id' => $staffProfile->id,
            'price_at_booking' => 500,
            'duration_minutes_at_booking' => 45,
            'start_at' => $appointment->start_at,
            'end_at' => $appointment->end_at,
        ]);

        $response = $this->actingAs($frontDesk)->postToTenant("/appointments/{$appointment->id}/bill", []);

        $response->assertRedirect();
        $this->assertDatabaseHas('bills', ['appointment_id' => $appointment->id, 'client_id' => $appointment->client_id]);
        $this->assertDatabaseHas('bill_line_items', ['description' => $service->name, 'unit_price' => 423.72]);
    }

    public function test_generated_bill_includes_manual_retail_line_items(): void
    {
        $frontDesk = User::factory()->for($this->tenant)->create();
        $frontDesk->assignRole('FrontDesk');

        $appointment = Appointment::factory()->create(['tenant_id' => $this->tenant->id, 'status' => 'completed']);
        $staffProfile = StaffProfile::factory()->create(['tenant_id' => $this->tenant->id]);
        $service = Service::factory()->create(['tenant_id' => $this->tenant->id, 'price' => 500]);
        $appointment->services()->attach($service, [
            'staff_profile_id' => $staffProfile->id,
            'price_at_booking' => 500,
            'duration_minutes_at_booking' => 45,
            'start_at' => $appointment->start_at,
            'end_at' => $appointment->end_at,
        ]);

        $response = $this->actingAs($frontDesk)->postToTenant("/appointments/{$appointment->id}/bill", [
            'manual_items' => [
                ['description' => 'Retail shampoo', 'unit_price' => 350],
            ],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('bill_line_items', ['description' => 'Retail shampoo', 'unit_price' => 296.61]);
    }
}
