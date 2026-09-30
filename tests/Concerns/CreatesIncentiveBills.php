<?php

namespace Tests\Concerns;

use App\Models\Bill;
use App\Models\BillLineItem;
use App\Models\StaffProfile;
use App\Services\BranchContext;
use App\Services\TenantContext;

/**
 * Requires ActsAsTenant. Sets the tenant and branch contexts so that
 * tenant- and branch-scoped models can be created and queried in tests.
 */
trait CreatesIncentiveBills
{
    protected function useTenantAndBranchContext(): void
    {
        app(TenantContext::class)->set($this->tenant);

        if ($this->branch === null) {
            $this->setUpBranch();
        }

        app(BranchContext::class)->set($this->branch);
    }

    protected function billWithLine(
        string $lineTotal,
        StaffProfile $servicing,
        ?StaffProfile $referrer = null,
        string $status = Bill::StatusPaid,
        string $createdAt = '2026-06-10',
        string $gst = '0',
        string $discount = '0',
    ): Bill {
        $bill = Bill::factory()->create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'status' => $status,
            'created_at' => $createdAt,
        ]);

        BillLineItem::factory()->create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'bill_id' => $bill->id,
            'staff_profile_id' => $servicing->id,
            'referred_by_staff_profile_id' => $referrer?->id,
            'line_total' => $lineTotal,
            'discount_amount' => $discount,
            'cgst_amount' => bcdiv($gst, '2', 2),
            'sgst_amount' => bcdiv($gst, '2', 2),
            'igst_amount' => 0,
        ]);

        return $bill;
    }
}
