<?php

namespace App\Models;

use App\Models\Scopes\BranchScope;
use App\Models\Scopes\TenantScope;
use Database\Factories\BillLineItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id', 'branch_id', 'bill_id', 'service_id', 'combo_service_id', 'combo_group', 'target_amount', 'staff_profile_id', 'referred_by_staff_profile_id', 'description',
    'quantity', 'unit_price', 'tax_rate', 'line_total', 'discount_amount', 'cgst_amount', 'sgst_amount', 'igst_amount',
])]
#[ScopedBy([TenantScope::class, BranchScope::class])]
class BillLineItem extends Model
{
    /** @use HasFactory<BillLineItemFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'target_amount' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'line_total' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'cgst_amount' => 'decimal:2',
            'sgst_amount' => 'decimal:2',
            'igst_amount' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return BelongsTo<Bill, $this> */
    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }

    /** @return BelongsTo<Service, $this> */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /** @return BelongsTo<Service, $this> */
    public function comboService(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'combo_service_id');
    }

    /** @return BelongsTo<StaffProfile, $this> */
    public function staffProfile(): BelongsTo
    {
        return $this->belongsTo(StaffProfile::class);
    }

    /** @return BelongsTo<StaffProfile, $this> */
    public function referredByStaffProfile(): BelongsTo
    {
        return $this->belongsTo(StaffProfile::class, 'referred_by_staff_profile_id');
    }

    public function taxAmount(): string
    {
        $taxableAmount = bcsub((string) $this->line_total, (string) $this->discount_amount, 2);

        return bcmul($taxableAmount, bcdiv((string) $this->tax_rate, '100', 4), 2);
    }

    /**
     * The value credited to staff targets and incentives. Combo lines credit
     * the amount given for the service, without GST; every other line credits
     * its GST-inclusive value after discount.
     */
    public function targetValue(): string
    {
        if ($this->target_amount === null) {
            return $this->totalWithTax();
        }

        return bcsub((string) $this->target_amount, (string) $this->discount_amount, 2);
    }

    public function totalWithTax(): string
    {
        $taxableAmount = bcsub((string) $this->line_total, (string) $this->discount_amount, 2);
        $gst = bcadd(bcadd((string) $this->cgst_amount, (string) $this->sgst_amount, 2), (string) $this->igst_amount, 2);

        return bcadd($taxableAmount, $gst, 2);
    }
}
