<?php

namespace Tests\Regression\BridalEngagements;

use App\Models\Bill;
use App\Models\Branch;
use App\Models\BridalEngagement;
use App\Models\Client;
use App\Models\Tenant;
use App\Repositories\Contracts\BridalEngagementRepositoryInterface;
use App\Services\BranchContext;
use App\Services\BridalEngagementService;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class EngagementRollsBackClientOnCreateFailureFixTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($this->tenant);
        app(BranchContext::class)->set(Branch::factory()->create(['tenant_id' => $this->tenant->id]));
    }

    /**
     * Bug risk: the bride is created as a client before the engagement row.
     * If the engagement insert fails, the new client must not be left behind.
     */
    public function test_failed_engagement_create_leaves_no_orphan_client(): void
    {
        $repository = Mockery::mock(BridalEngagementRepositoryInterface::class);
        $repository->shouldReceive('create')->andThrow(new RuntimeException('insert failed'));
        $this->app->instance(BridalEngagementRepositoryInterface::class, $repository);

        try {
            app(BridalEngagementService::class)->createEngagement([
                'contact_number' => '9000000001',
                'bride_name' => 'Orphan Bride',
                'event_date' => now()->addMonth()->toDateString(),
                'venue_type' => 'studio',
                'ready_time' => '07:00',
                'total_amount' => 1000,
            ]);

            $this->fail('Expected RuntimeException was not thrown.');
        } catch (RuntimeException) {
            // expected
        }

        $this->assertSame(0, Client::where('phone', '9000000001')->count());
    }

    public function test_void_bill_is_excluded_from_event_collected_total(): void
    {
        $engagement = BridalEngagement::factory()->create(['tenant_id' => $this->tenant->id]);
        Bill::factory()->create([
            'tenant_id' => $this->tenant->id,
            'bridal_engagement_id' => $engagement->id,
            'status' => Bill::StatusVoid,
            'total' => 5000,
            'amount_paid' => 5000,
        ]);

        $summary = app(BridalEngagementService::class)->summarize($engagement->load('bills'));

        $this->assertSame('0.00', $summary['billed']);
        $this->assertSame('0.00', $summary['collected']);
    }
}
