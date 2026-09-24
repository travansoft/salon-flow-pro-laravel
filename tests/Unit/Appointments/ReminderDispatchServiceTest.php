<?php

namespace Tests\Unit\Appointments;

use App\Models\AppointmentReminder;
use App\Models\Branch;
use App\Models\Tenant;
use App\Services\BranchContext;
use App\Services\Contracts\ReminderChannelInterface;
use App\Services\ReminderDispatchService;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ReminderDispatchServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispatch_due_sends_only_pending_reminders_scheduled_in_the_past(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);

        $due = AppointmentReminder::factory()->due()->create(['tenant_id' => $tenant->id]);
        AppointmentReminder::factory()->create(['tenant_id' => $tenant->id, 'scheduled_for' => now()->addHour()]);

        $channel = Mockery::mock(ReminderChannelInterface::class);
        $channel->shouldReceive('send')->once()->with(Mockery::on(fn ($reminder) => $reminder->id === $due->id))->andReturn(true);

        $count = (new ReminderDispatchService($channel))->dispatchDue();

        $this->assertSame(1, $count);
        $this->assertSame('sent', $due->fresh()->status);
    }

    public function test_dispatch_due_marks_failed_when_channel_send_fails(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);

        $due = AppointmentReminder::factory()->due()->create(['tenant_id' => $tenant->id]);

        $channel = Mockery::mock(ReminderChannelInterface::class);
        $channel->shouldReceive('send')->once()->andReturn(false);

        (new ReminderDispatchService($channel))->dispatchDue();

        $this->assertSame('failed', $due->fresh()->status);
        $this->assertNull($due->fresh()->sent_at);
    }
}
