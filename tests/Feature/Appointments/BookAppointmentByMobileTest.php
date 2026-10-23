<?php

namespace Tests\Feature\Appointments;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\StaffShift;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsTenant;
use Tests\TestCase;

class BookAppointmentByMobileTest extends TestCase
{
    use ActsAsTenant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenant();
        $this->seed(PermissionSeeder::class);
    }

    /** @return array<string, mixed> */
    private function bookingPayload(): array
    {
        $start = now()->next(1)->setTime(10, 0);
        $staffProfile = StaffProfile::factory()->create(['tenant_id' => $this->tenant->id]);
        StaffShift::factory()->create([
            'tenant_id' => $this->tenant->id,
            'staff_profile_id' => $staffProfile->id,
            'day_of_week' => $start->dayOfWeek,
            'start_time' => '09:00',
            'end_time' => '18:00',
            'is_working' => true,
        ]);
        $service = Service::factory()->create(['tenant_id' => $this->tenant->id]);

        return [
            'start_at' => $start->toDateTimeString(),
            'services' => [['service_id' => $service->id, 'staff_profile_id' => $staffProfile->id]],
        ];
    }

    private function frontDesk(): User
    {
        $frontDesk = User::factory()->for($this->tenant)->create();
        $frontDesk->assignRole('FrontDesk');

        return $frontDesk;
    }

    public function test_booking_with_unknown_mobile_creates_the_client(): void
    {
        $response = $this->actingAs($this->frontDesk())->postToTenant('/appointments', [
            ...$this->bookingPayload(),
            'client_name' => 'Priya Nair',
            'client_phone' => '9000000123',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('clients', ['name' => 'Priya Nair', 'phone' => '9000000123', 'tenant_id' => $this->tenant->id]);
        $this->assertDatabaseCount('appointments', 1);
    }

    public function test_booking_with_existing_mobile_reuses_the_client(): void
    {
        $client = Client::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Anjali Menon', 'phone' => '9000000456']);

        $this->actingAs($this->frontDesk())->postToTenant('/appointments', [
            ...$this->bookingPayload(),
            'client_name' => 'Someone Else',
            'client_phone' => '9000000456',
        ]);

        $this->assertDatabaseCount('clients', 1);
        $this->assertDatabaseHas('appointments', ['client_id' => $client->id]);
        $this->assertDatabaseHas('clients', ['id' => $client->id, 'name' => 'Anjali Menon']);
    }

    public function test_name_and_mobile_are_required_without_a_client_id(): void
    {
        $response = $this->actingAs($this->frontDesk())->postToTenant('/appointments', $this->bookingPayload());

        $response->assertSessionHasErrors(['client_name', 'client_phone']);
    }

    public function test_create_form_has_no_explicit_client_creation(): void
    {
        $response = $this->actingAs($this->frontDesk())->getFromTenant('/appointments/create');

        $response->assertOk();
        $response->assertDontSee('Create client');
        $response->assertSee('Mobile number');
        $response->assertDontSee('Time slot');
    }

    public function test_chosen_to_time_is_saved_as_appointment_end(): void
    {
        $payload = $this->bookingPayload();

        $this->actingAs($this->frontDesk())->postToTenant('/appointments', [
            ...$payload,
            'end_at' => Carbon::parse($payload['start_at'])->setTime(12, 30)->toDateTimeString(),
            'client_name' => 'Priya Nair',
            'client_phone' => '9000000789',
        ]);

        $appointment = Appointment::firstOrFail();
        $this->assertSame('12:30', $appointment->end_at->format('H:i'));
    }

    public function test_to_time_must_be_after_from_time(): void
    {
        $payload = $this->bookingPayload();

        $response = $this->actingAs($this->frontDesk())->postToTenant('/appointments', [
            ...$payload,
            'end_at' => Carbon::parse($payload['start_at'])->subHour()->toDateTimeString(),
            'client_name' => 'Priya Nair',
            'client_phone' => '9000000790',
        ]);

        $response->assertSessionHasErrors('end_at');
    }
}
