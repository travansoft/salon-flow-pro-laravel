<?php

namespace Tests\Unit\Billing;

use App\Models\Appointment;
use App\Models\Bill;
use App\Models\Client;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\Tenant;
use App\Models\User;
use App\Services\BillingService;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class BillingServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_manual_bill_computes_subtotal_tax_and_total_correctly(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $user = User::factory()->for($tenant)->create();

        $bill = app(BillingService::class)->createManualBill($client->id, $user->id, [
            ['description' => 'Hair Color', 'unit_price' => 1000, 'tax_rate' => 18],
        ]);

        $this->assertSame('847.45', (string) $bill->subtotal);
        $this->assertSame('152.55', (string) $bill->tax_amount);
        $this->assertSame('1000.00', (string) $bill->total);
    }

    public function test_bill_numbers_are_sequential_per_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $user = User::factory()->for($tenant)->create();

        $first = app(BillingService::class)->createManualBill($client->id, $user->id, [
            ['description' => 'Item A', 'unit_price' => 100],
        ]);
        $second = app(BillingService::class)->createManualBill($client->id, $user->id, [
            ['description' => 'Item B', 'unit_price' => 100],
        ]);

        $this->assertSame($first->bill_number + 1, $second->bill_number);
    }

    public function test_create_bill_rejects_empty_line_items(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $user = User::factory()->for($tenant)->create();

        $this->expectException(InvalidArgumentException::class);

        app(BillingService::class)->createManualBill($client->id, $user->id, []);
    }

    public function test_create_manual_bill_persists_staff_profile_id_on_line_item(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $user = User::factory()->for($tenant)->create();
        $service = Service::factory()->create(['tenant_id' => $tenant->id]);
        $staffProfile = StaffProfile::factory()->create(['tenant_id' => $tenant->id]);
        $service->staff()->sync([$staffProfile->id]);

        $bill = app(BillingService::class)->createManualBill($client->id, $user->id, [
            ['description' => 'Haircut', 'service_id' => $service->id, 'staff_profile_id' => $staffProfile->id, 'unit_price' => 500],
        ]);

        $this->assertSame($staffProfile->id, $bill->lineItems->first()->staff_profile_id);
    }

    public function test_create_manual_bill_allows_null_staff_profile_id_for_manual_items(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $user = User::factory()->for($tenant)->create();

        $bill = app(BillingService::class)->createManualBill($client->id, $user->id, [
            ['description' => 'Retail item', 'unit_price' => 250],
        ]);

        $this->assertNull($bill->lineItems->first()->staff_profile_id);
    }

    public function test_record_payments_supports_split_across_methods_and_marks_paid_when_fully_covered(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $user = User::factory()->for($tenant)->create();

        $bill = app(BillingService::class)->createManualBill($client->id, $user->id, [
            ['description' => 'Facial', 'unit_price' => 1000, 'tax_rate' => 18],
        ]);

        $updated = app(BillingService::class)->recordPayments($bill, [
            ['method' => 'cash', 'amount' => 700],
            ['method' => 'card', 'amount' => 480],
        ], $user->id);

        $this->assertSame(Bill::StatusPaid, $updated->status);
        $this->assertSame('1180.00', (string) $updated->amount_paid);
        $this->assertSame(2, $updated->payments()->count());
    }

    public function test_record_partial_payment_marks_bill_as_partial(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $user = User::factory()->for($tenant)->create();

        $bill = app(BillingService::class)->createManualBill($client->id, $user->id, [
            ['description' => 'Facial', 'unit_price' => 1000, 'tax_rate' => 18],
        ]);

        $updated = app(BillingService::class)->recordPayments($bill, [
            ['method' => 'cash', 'amount' => 500],
        ], $user->id);

        $this->assertSame(Bill::StatusPartial, $updated->status);
    }

    public function test_refund_rejects_amount_greater_than_paid(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $user = User::factory()->for($tenant)->create();

        $bill = app(BillingService::class)->createManualBill($client->id, $user->id, [
            ['description' => 'Facial', 'unit_price' => 1000, 'tax_rate' => 18],
        ]);
        app(BillingService::class)->recordPayments($bill, [['method' => 'cash', 'amount' => 500]], $user->id);

        $this->expectException(InvalidArgumentException::class);

        app(BillingService::class)->refund($bill, 600, 'Client complaint', $user->id);
    }

    public function test_refund_within_paid_amount_succeeds_and_tracks_reason(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $user = User::factory()->for($tenant)->create();

        $bill = app(BillingService::class)->createManualBill($client->id, $user->id, [
            ['description' => 'Facial', 'unit_price' => 1000, 'tax_rate' => 18],
        ]);
        app(BillingService::class)->recordPayments($bill, [['method' => 'cash', 'amount' => 1180]], $user->id);

        $refunded = app(BillingService::class)->refund($bill, 200, 'Service not completed', $user->id);

        $this->assertSame('200.00', (string) $refunded->amount_refunded);
        $this->assertDatabaseHas('bill_refunds', ['bill_id' => $bill->id, 'reason' => 'Service not completed']);
    }

    public function test_intra_state_bill_splits_tax_into_cgst_and_sgst(): void
    {
        $tenant = Tenant::factory()->create(['gst_state_code' => '32']);
        app(TenantContext::class)->set($tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id, 'gst_number' => '32AAAAA0000A1Z5']);
        $user = User::factory()->for($tenant)->create();

        $bill = app(BillingService::class)->createManualBill($client->id, $user->id, [
            ['description' => 'Hair Color', 'unit_price' => 1000, 'tax_rate' => 18],
        ]);

        $this->assertSame('76.27', (string) $bill->cgst_amount);
        $this->assertSame('76.28', (string) $bill->sgst_amount);
        $this->assertSame('0.00', (string) $bill->igst_amount);
    }

    public function test_inter_state_bill_charges_igst_only(): void
    {
        $tenant = Tenant::factory()->create(['gst_state_code' => '32']);
        app(TenantContext::class)->set($tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id, 'gst_number' => '27AAAAA0000A1Z5']);
        $user = User::factory()->for($tenant)->create();

        $bill = app(BillingService::class)->createManualBill($client->id, $user->id, [
            ['description' => 'Hair Color', 'unit_price' => 1000, 'tax_rate' => 18],
        ]);

        $this->assertSame('0.00', (string) $bill->cgst_amount);
        $this->assertSame('0.00', (string) $bill->sgst_amount);
        $this->assertSame('152.55', (string) $bill->igst_amount);
    }

    public function test_client_without_gstin_is_treated_as_intra_state(): void
    {
        $tenant = Tenant::factory()->create(['gst_state_code' => '32']);
        app(TenantContext::class)->set($tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id, 'gst_number' => null]);
        $user = User::factory()->for($tenant)->create();

        $bill = app(BillingService::class)->createManualBill($client->id, $user->id, [
            ['description' => 'Hair Color', 'unit_price' => 1000, 'tax_rate' => 18],
        ]);

        $this->assertSame('76.27', (string) $bill->cgst_amount);
        $this->assertSame('76.28', (string) $bill->sgst_amount);
    }

    public function test_service_tax_rate_falls_back_to_tenant_default_when_unset(): void
    {
        $tenant = Tenant::factory()->create(['default_gst_rate' => 12]);
        app(TenantContext::class)->set($tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $user = User::factory()->for($tenant)->create();
        $service = Service::factory()->create(['tenant_id' => $tenant->id, 'price' => 1000, 'tax_rate' => null]);

        $appointment = Appointment::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        $staffProfile = StaffProfile::factory()->create(['tenant_id' => $tenant->id]);
        $appointment->services()->attach($service, [
            'staff_profile_id' => $staffProfile->id,
            'price_at_booking' => 1000,
            'duration_minutes_at_booking' => 30,
            'start_at' => $appointment->start_at,
            'end_at' => $appointment->end_at,
        ]);

        $bill = app(BillingService::class)->generateFromAppointment($appointment, $user->id);

        $this->assertSame('107.15', (string) $bill->tax_amount);
    }

    public function test_service_specific_tax_rate_overrides_tenant_default(): void
    {
        $tenant = Tenant::factory()->create(['default_gst_rate' => 12]);
        app(TenantContext::class)->set($tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $user = User::factory()->for($tenant)->create();
        $service = Service::factory()->create(['tenant_id' => $tenant->id, 'price' => 1000, 'tax_rate' => 5]);

        $appointment = Appointment::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        $staffProfile = StaffProfile::factory()->create(['tenant_id' => $tenant->id]);
        $appointment->services()->attach($service, [
            'staff_profile_id' => $staffProfile->id,
            'price_at_booking' => 1000,
            'duration_minutes_at_booking' => 30,
            'start_at' => $appointment->start_at,
            'end_at' => $appointment->end_at,
        ]);

        $bill = app(BillingService::class)->generateFromAppointment($appointment, $user->id);

        $this->assertSame('47.62', (string) $bill->tax_amount);
    }

    public function test_discount_reduces_the_taxable_amount_before_gst_is_applied(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $user = User::factory()->for($tenant)->create();

        $bill = app(BillingService::class)->createManualBill($client->id, $user->id, [
            ['description' => 'Hair Color', 'unit_price' => 1000, 'tax_rate' => 18],
        ], 10);

        $this->assertSame('847.45', (string) $bill->subtotal);
        $this->assertSame('10.00', (string) $bill->discount_percent);
        $this->assertSame('84.74', (string) $bill->discount_amount);
        $this->assertSame('137.28', (string) $bill->tax_amount);
        $this->assertSame('899.99', (string) $bill->total);
    }

    public function test_zero_discount_leaves_the_bill_unchanged(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $user = User::factory()->for($tenant)->create();

        $bill = app(BillingService::class)->createManualBill($client->id, $user->id, [
            ['description' => 'Hair Color', 'unit_price' => 1000, 'tax_rate' => 18],
        ]);

        $this->assertSame('0.00', (string) $bill->discount_amount);
        $this->assertSame('1000.00', (string) $bill->total);
    }

    public function test_discount_percent_above_100_is_rejected(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $user = User::factory()->for($tenant)->create();

        $this->expectException(InvalidArgumentException::class);

        app(BillingService::class)->createManualBill($client->id, $user->id, [
            ['description' => 'Hair Color', 'unit_price' => 1000, 'tax_rate' => 18],
        ], 150);
    }

    public function test_discount_is_split_proportionally_across_multiple_line_items(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $user = User::factory()->for($tenant)->create();

        $bill = app(BillingService::class)->createManualBill($client->id, $user->id, [
            ['description' => 'Hair Color', 'unit_price' => 100, 'tax_rate' => 5],
            ['description' => 'Spa Package', 'unit_price' => 200, 'tax_rate' => 18],
        ], 10);

        $first = $bill->lineItems->firstWhere('description', 'Hair Color');
        $second = $bill->lineItems->firstWhere('description', 'Spa Package');

        $this->assertSame('9.52', (string) $first->discount_amount);
        $this->assertSame('16.94', (string) $second->discount_amount);
        $this->assertSame('26.46', (string) $bill->discount_amount);
    }
}
