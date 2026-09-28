<?php

namespace Tests\Unit\Billing;

use App\Models\Bill;
use App\Models\Branch;
use App\Models\Tenant;
use App\Repositories\Contracts\BillRepositoryInterface;
use App\Services\BranchContext;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillNotesTest extends TestCase
{
    use RefreshDatabase;

    public function test_repository_update_persists_a_note_on_the_bill(): void
    {
        [$bill] = $this->makeBillInContext();

        app(BillRepositoryInterface::class)->update($bill, ['notes' => 'Client asked for a discount, approved verbally.']);

        $this->assertSame('Client asked for a discount, approved verbally.', $bill->fresh()->notes);
    }

    public function test_note_defaults_to_null_when_not_set(): void
    {
        [$bill] = $this->makeBillInContext();

        $this->assertNull($bill->notes);
    }

    public function test_note_can_be_cleared_by_setting_it_to_null(): void
    {
        [$bill] = $this->makeBillInContext();

        app(BillRepositoryInterface::class)->update($bill, ['notes' => 'Temporary note']);
        app(BillRepositoryInterface::class)->update($bill, ['notes' => null]);

        $this->assertNull($bill->fresh()->notes);
    }

    /** @return array{0: Bill} */
    private function makeBillInContext(): array
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);

        $bill = Bill::factory()->create(['tenant_id' => $tenant->id]);

        return [$bill];
    }
}
