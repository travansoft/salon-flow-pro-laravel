<?php

namespace Tests\Integration\TimeSlots;

use App\Models\Branch;
use App\Models\Tenant;
use App\Models\TimeSlot;
use App\Services\BranchContext;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TimeSlotDatabaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_time_slots_are_scoped_per_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        TimeSlot::factory()->create(['tenant_id' => $tenantA->id]);
        TimeSlot::factory()->create(['tenant_id' => $tenantB->id]);

        app(TenantContext::class)->set($tenantA);
        $branchA = Branch::factory()->create(['tenant_id' => $tenantA->id]);
        app(BranchContext::class)->set($branchA);
        $this->assertSame(1, TimeSlot::count());
    }

    public function test_label_formats_start_and_end_time(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);
        $slot = TimeSlot::factory()->create([
            'tenant_id' => $tenant->id,
            'start_time' => '09:00:00',
            'end_time' => '09:30:00',
        ]);

        $this->assertSame('09:00 – 09:30', $slot->label());
    }

    public function test_deleting_a_time_slot_soft_deletes_it(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);
        $slot = TimeSlot::factory()->create(['tenant_id' => $tenant->id]);

        $slot->delete();

        $this->assertSoftDeleted('time_slots', ['id' => $slot->id]);
    }
}
