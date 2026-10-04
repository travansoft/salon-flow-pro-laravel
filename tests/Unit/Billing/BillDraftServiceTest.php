<?php

namespace Tests\Unit\Billing;

use App\Models\BillDraft;
use App\Models\Branch;
use App\Models\Tenant;
use App\Models\User;
use App\Repositories\Contracts\BillDraftRepositoryInterface;
use App\Services\BillDraftService;
use App\Services\BranchContext;
use App\Services\TenantContext;
use Mockery;
use Tests\TestCase;

class BillDraftServiceTest extends TestCase
{
    private function service(BillDraftRepositoryInterface $repository): BillDraftService
    {
        $tenant = new Tenant;
        $tenant->id = 7;
        $branch = new Branch;
        $branch->id = 3;

        $tenantContext = new TenantContext;
        $tenantContext->set($tenant);
        $branchContext = new BranchContext;
        $branchContext->set($branch);

        return new BillDraftService($repository, $tenantContext, $branchContext);
    }

    private function user(): User
    {
        $user = new User;
        $user->id = 11;

        return $user;
    }

    public function test_save_creates_a_draft_with_display_fields_computed_from_the_payload(): void
    {
        $repository = Mockery::mock(BillDraftRepositoryInterface::class);
        $repository->shouldReceive('create')
            ->once()
            ->withArgs(fn (array $data): bool => $data['tenant_id'] === 7
                && $data['branch_id'] === 3
                && $data['user_id'] === 11
                && $data['client_name'] === 'Asha'
                && $data['client_phone'] === null
                && $data['item_count'] === 2
                && $data['total'] === 900.0)
            ->andReturn(new BillDraft);

        $this->service($repository)->save($this->user(), [
            'client_name' => ' Asha ',
            'client_phone' => '  ',
            'discount_mode' => 'percent',
            'discount_value' => 10,
            'lines' => [
                ['priceInclusive' => 500, 'quantity' => 1],
                ['priceInclusive' => 250, 'quantity' => 2],
            ],
        ]);
    }

    public function test_amount_discount_never_exceeds_the_subtotal(): void
    {
        $repository = Mockery::mock(BillDraftRepositoryInterface::class);
        $repository->shouldReceive('create')
            ->once()
            ->withArgs(fn (array $data): bool => $data['total'] === 0.0)
            ->andReturn(new BillDraft);

        $this->service($repository)->save($this->user(), [
            'discount_mode' => 'amount',
            'discount_value' => 5000,
            'lines' => [['priceInclusive' => 500, 'quantity' => 1]],
        ]);
    }

    public function test_save_returns_null_when_updating_a_draft_the_user_does_not_own(): void
    {
        $repository = Mockery::mock(BillDraftRepositoryInterface::class);
        $repository->shouldReceive('findForUser')->once()->with(99, 11)->andReturnNull();
        $repository->shouldReceive('update')->never();

        $result = $this->service($repository)->save($this->user(), ['client_name' => 'Asha'], 99);

        $this->assertNull($result);
    }

    public function test_discard_does_not_delete_when_draft_is_not_found_for_user(): void
    {
        $repository = Mockery::mock(BillDraftRepositoryInterface::class);
        $repository->shouldReceive('findForUser')->once()->with(99, 11)->andReturnNull();
        $repository->shouldReceive('delete')->never();

        $this->assertFalse($this->service($repository)->discard($this->user(), 99));
    }

    public function test_discard_deletes_the_users_draft(): void
    {
        $draft = new BillDraft;
        $repository = Mockery::mock(BillDraftRepositoryInterface::class);
        $repository->shouldReceive('findForUser')->once()->with(5, 11)->andReturn($draft);
        $repository->shouldReceive('delete')->once()->with($draft);

        $this->assertTrue($this->service($repository)->discard($this->user(), 5));
    }
}
