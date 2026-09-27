<?php

namespace App\Console\Commands;

use App\Repositories\Contracts\TenantRepositoryInterface;
use App\Services\BranchContext;
use App\Services\ReminderDispatchService;
use App\Services\TenantContext;
use Illuminate\Console\Command;

class SendDueAppointmentReminders extends Command
{
    /** @var string */
    protected $signature = 'reminders:send-due';

    /** @var string */
    protected $description = 'Send all appointment confirmation and reminder messages that are due, across every active tenant';

    public function handle(
        TenantRepositoryInterface $tenantRepository,
        TenantContext $tenantContext,
        BranchContext $branchContext,
        ReminderDispatchService $reminderDispatchService,
    ): int {
        $total = 0;

        foreach ($tenantRepository->getAllActive() as $tenant) {
            $this->info("Processing tenant: {$tenant->name}...");

            $tenantContext->set($tenant);
            $branchContext->bypass();
            $total += $reminderDispatchService->dispatchDue();
        }

        $this->comment("Dispatched {$total} due reminder(s) across all tenants.");

        return self::SUCCESS;
    }
}
