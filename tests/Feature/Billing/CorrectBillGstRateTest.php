<?php

namespace Tests\Feature\Billing;

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsTenant;
use Tests\Concerns\CreatesInclusiveBills;
use Tests\TestCase;

class CorrectBillGstRateTest extends TestCase
{
    use ActsAsTenant, CreatesInclusiveBills, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenant();
        $this->setUpBranch();
        $this->seed(PermissionSeeder::class);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->for($this->tenant)->create();
        $user->assignRole($role);
        $this->assignToBranch($user);

        return $user;
    }

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return [
            'from_rate' => 18,
            'to_rate' => 5,
            'from_date' => now()->toDateString(),
            'to_date' => now()->toDateString(),
            ...$overrides,
        ];
    }

    public function test_owner_can_open_the_correction_page(): void
    {
        $response = $this->actingAs($this->userWithRole('Owner'))->getFromTenant('/bills/gst-correction');

        $response->assertOk()->assertSee('Correct GST rate');
    }

    public function test_preview_lists_the_bills_without_changing_them(): void
    {
        $bill = $this->billFor($this->tenant, [['description' => 'Hair Color', 'unit_price' => 1000, 'tax_rate' => 18]]);

        $response = $this->actingAs($this->userWithRole('Owner'))->postToTenant('/bills/gst-correction', $this->payload());

        $response->assertOk()->assertSee('1 bill(s)')->assertSee($bill->invoiceNumber());
        $this->assertDatabaseHas('bills', ['id' => $bill->id, 'tax_amount' => 152.55]);
    }

    public function test_confirming_applies_the_new_rate_to_bills_in_range(): void
    {
        $bill = $this->billFor($this->tenant, [['description' => 'Hair Color', 'unit_price' => 1000, 'tax_rate' => 18]]);

        $response = $this->actingAs($this->userWithRole('Owner'))->postToTenant('/bills/gst-correction', $this->payload(['confirm' => 1]));

        $response->assertRedirect();
        $this->assertDatabaseHas('bills', ['id' => $bill->id, 'tax_amount' => 47.62, 'total' => 1000]);
    }

    public function test_bills_outside_the_date_range_are_not_changed(): void
    {
        $bill = $this->billFor($this->tenant, [['description' => 'Hair Color', 'unit_price' => 1000, 'tax_rate' => 18]]);

        $this->actingAs($this->userWithRole('Owner'))->postToTenant('/bills/gst-correction', $this->payload([
            'confirm' => 1,
            'from_date' => now()->subDays(10)->toDateString(),
            'to_date' => now()->subDays(5)->toDateString(),
        ]));

        $this->assertDatabaseHas('bills', ['id' => $bill->id, 'tax_amount' => 152.55]);
    }

    public function test_same_old_and_new_rate_is_rejected(): void
    {
        $response = $this->actingAs($this->userWithRole('Owner'))->postToTenant('/bills/gst-correction', $this->payload(['to_rate' => 18]));

        $response->assertSessionHasErrors('to_rate');
    }

    public function test_end_date_before_start_date_is_rejected(): void
    {
        $response = $this->actingAs($this->userWithRole('Owner'))->postToTenant('/bills/gst-correction', $this->payload([
            'from_date' => now()->toDateString(),
            'to_date' => now()->subDay()->toDateString(),
        ]));

        $response->assertSessionHasErrors('to_date');
    }

    public function test_manager_cannot_correct_gst(): void
    {
        $response = $this->actingAs($this->userWithRole('Manager'))->postToTenant('/bills/gst-correction', $this->payload(['confirm' => 1]));

        $response->assertForbidden();
    }

    public function test_front_desk_cannot_open_the_correction_page(): void
    {
        $response = $this->actingAs($this->userWithRole('FrontDesk'))->getFromTenant('/bills/gst-correction');

        $response->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->getFromTenant('/bills/gst-correction');

        $response->assertRedirect('/login');
    }
}
