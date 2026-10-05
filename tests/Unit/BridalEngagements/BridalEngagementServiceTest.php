<?php

namespace Tests\Unit\BridalEngagements;

use App\Models\Bill;
use App\Models\Branch;
use App\Models\BridalEngagement;
use App\Models\Client;
use App\Models\Tenant;
use App\Repositories\Contracts\BillRepositoryInterface;
use App\Repositories\Contracts\BridalEngagementRepositoryInterface;
use App\Repositories\Contracts\ClientRepositoryInterface;
use App\Services\BillingService;
use App\Services\BranchContext;
use App\Services\BridalEngagementService;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class BridalEngagementServiceTest extends TestCase
{
    use RefreshDatabase;

    private MockInterface $engagements;

    private MockInterface $clients;

    private MockInterface $bills;

    private MockInterface $billing;

    protected function setUp(): void
    {
        parent::setUp();

        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        app(BranchContext::class)->set(Branch::factory()->create(['tenant_id' => $tenant->id]));

        $this->engagements = Mockery::mock(BridalEngagementRepositoryInterface::class);
        $this->clients = Mockery::mock(ClientRepositoryInterface::class);
        $this->bills = Mockery::mock(BillRepositoryInterface::class);
        $this->billing = Mockery::mock(BillingService::class);
    }

    private function service(): BridalEngagementService
    {
        return new BridalEngagementService(
            $this->engagements,
            $this->clients,
            $this->bills,
            $this->billing,
            app(TenantContext::class),
            app(BranchContext::class),
        );
    }

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return [
            'contact_number' => '9999900000',
            'bride_name' => 'Anjali',
            'event_name' => 'Wedding',
            'event_date' => '2026-12-01',
            'venue_type' => 'home',
            'home_location' => '12 Lake Road',
            'has_studio_trial' => false,
            'trial_date' => '2026-11-20',
            'ready_time' => '06:30',
            'total_amount' => 30000,
            'advance_amount' => 5000,
            'dress_type' => 'others',
            'saree_drapist_name' => 'Should be dropped',
            ...$overrides,
        ];
    }

    public function test_create_reuses_existing_client_found_by_phone(): void
    {
        $client = Client::factory()->create();
        $this->clients->shouldReceive('findByPhone')->once()->with('9999900000')->andReturn($client);
        $this->clients->shouldReceive('create')->never();
        $this->engagements->shouldReceive('create')->once()
            ->withArgs(fn (array $data) => $data['client_id'] === $client->id && $data['status'] === BridalEngagement::StatusPlanned)
            ->andReturn(new BridalEngagement);

        $this->service()->createEngagement($this->payload());
    }

    public function test_create_makes_a_new_client_when_phone_is_unknown(): void
    {
        $client = Client::factory()->create();
        $this->clients->shouldReceive('findByPhone')->once()->andReturn(null);
        $this->clients->shouldReceive('create')->once()
            ->withArgs(fn (array $data) => $data['name'] === 'Anjali' && $data['phone'] === '9999900000')
            ->andReturn($client);
        $this->engagements->shouldReceive('create')->once()->andReturn(new BridalEngagement);

        $this->service()->createEngagement($this->payload());
    }

    public function test_create_clears_conditional_fields_that_do_not_apply(): void
    {
        $client = Client::factory()->create();
        $this->clients->shouldReceive('findByPhone')->andReturn($client);
        $this->engagements->shouldReceive('create')->once()
            ->withArgs(fn (array $data) => $data['trial_date'] === null
                && $data['saree_drapist_name'] === null
                && $data['home_location'] === '12 Lake Road')
            ->andReturn(new BridalEngagement);

        $this->service()->createEngagement($this->payload());
    }

    public function test_create_bill_requires_a_positive_amount(): void
    {
        $this->billing->shouldReceive('createManualBill')->never();

        $this->expectException(InvalidArgumentException::class);

        $this->service()->createBill(new BridalEngagement, 1, now(), 0, 'cash', [['staff_profile_id' => 1, 'amount' => 10]]);
    }

    public function test_create_bill_requires_staff(): void
    {
        $this->billing->shouldReceive('createManualBill')->never();

        $this->expectException(InvalidArgumentException::class);

        $this->service()->createBill(new BridalEngagement, 1, now(), 100, 'cash', []);
    }

    public function test_create_bill_rejects_duplicate_staff(): void
    {
        $this->billing->shouldReceive('createManualBill')->never();

        $this->expectException(InvalidArgumentException::class);

        $this->service()->createBill(new BridalEngagement, 1, now(), 100, 'cash', [
            ['staff_profile_id' => 1, 'amount' => 10],
            ['staff_profile_id' => 1, 'amount' => 20],
        ]);
    }

    public function test_find_bill_by_number_delegates_to_repository(): void
    {
        $bill = new Bill;
        $this->bills->shouldReceive('findByInvoiceNumber')->once()->with('42')->andReturn($bill);

        $this->assertSame($bill, $this->service()->findBillByNumber('42'));
    }

    public function test_attach_rejects_void_bill(): void
    {
        $this->bills->shouldReceive('update')->never();

        $this->expectException(InvalidArgumentException::class);

        $this->service()->attachBill(new BridalEngagement, new Bill(['status' => Bill::StatusVoid]));
    }

    public function test_attach_rejects_bill_linked_to_another_event(): void
    {
        $engagement = new BridalEngagement;
        $engagement->id = 1;
        $this->bills->shouldReceive('update')->never();

        $this->expectException(InvalidArgumentException::class);

        $this->service()->attachBill($engagement, new Bill(['status' => Bill::StatusUnpaid, 'bridal_engagement_id' => 2]));
    }

    public function test_detach_rejects_bill_not_attached_to_the_event(): void
    {
        $engagement = new BridalEngagement;
        $engagement->id = 1;
        $this->bills->shouldReceive('update')->never();

        $this->expectException(InvalidArgumentException::class);

        $this->service()->detachBill($engagement, new Bill(['bridal_engagement_id' => 2]));
    }

    public function test_summary_ignores_void_bills_and_nets_refunds(): void
    {
        $engagement = new BridalEngagement(['total_amount' => 30000, 'advance_amount' => 5000]);
        $engagement->setRelation('bills', collect([
            new Bill(['status' => Bill::StatusPartial, 'total' => 20000, 'amount_paid' => 12000, 'amount_refunded' => 2000]),
            new Bill(['status' => Bill::StatusVoid, 'total' => 9999, 'amount_paid' => 9999, 'amount_refunded' => 0]),
        ]));

        $summary = $this->service()->summarize($engagement);

        $this->assertSame('20000.00', $summary['billed']);
        $this->assertSame('10000.00', $summary['collected']);
        $this->assertSame('10000.00', $summary['outstanding']);
    }
}
