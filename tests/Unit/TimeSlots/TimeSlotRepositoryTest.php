<?php

namespace Tests\Unit\TimeSlots;

use App\Models\Branch;
use App\Models\Tenant;
use App\Models\TimeSlot;
use App\Repositories\Eloquent\TimeSlotRepository;
use App\Services\BranchContext;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TimeSlotRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_active_excludes_disabled_slots(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);
        TimeSlot::factory()->create(['tenant_id' => $tenant->id, 'start_time' => '09:00:00', 'end_time' => '09:30:00']);
        TimeSlot::factory()->inactive()->create(['tenant_id' => $tenant->id, 'start_time' => '10:00:00', 'end_time' => '10:30:00']);

        $repository = app(TimeSlotRepository::class);

        $this->assertCount(1, $repository->getActive());
    }

    public function test_get_all_returns_slots_ordered_by_start_time(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);
        TimeSlot::factory()->create(['tenant_id' => $tenant->id, 'start_time' => '11:00:00', 'end_time' => '11:30:00']);
        TimeSlot::factory()->create(['tenant_id' => $tenant->id, 'start_time' => '09:00:00', 'end_time' => '09:30:00']);

        $repository = app(TimeSlotRepository::class);

        $starts = $repository->getAll()->pluck('start_time')->all();

        $this->assertSame(['09:00:00', '11:00:00'], $starts);
    }

    public function test_delete_soft_deletes_the_slot(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);
        $slot = TimeSlot::factory()->create(['tenant_id' => $tenant->id]);

        $repository = app(TimeSlotRepository::class);
        $repository->delete($slot);

        $this->assertSoftDeleted('time_slots', ['id' => $slot->id]);
    }
}
