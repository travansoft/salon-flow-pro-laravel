<?php

namespace App\Models;

use App\Models\Scopes\BranchScope;
use App\Models\Scopes\TenantScope;
use Database\Factories\BillFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'tenant_id', 'branch_id', 'client_id', 'appointment_id', 'bill_number', 'financial_year', 'subtotal', 'tax_amount', 'total',
    'cgst_amount', 'sgst_amount', 'igst_amount', 'discount_percent', 'discount_amount',
    'amount_paid', 'amount_refunded', 'status', 'created_by',
])]
#[ScopedBy([TenantScope::class, BranchScope::class])]
class Bill extends Model
{
    /** @use HasFactory<BillFactory> */
    use HasFactory, SoftDeletes;

    public const StatusUnpaid = 'unpaid';

    public const StatusPartial = 'partial';

    public const StatusPaid = 'paid';

    public const StatusVoid = 'void';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'cgst_amount' => 'decimal:2',
            'sgst_amount' => 'decimal:2',
            'igst_amount' => 'decimal:2',
            'discount_percent' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'amount_refunded' => 'decimal:2',
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

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** @return BelongsTo<Appointment, $this> */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<BillLineItem, $this> */
    public function lineItems(): HasMany
    {
        return $this->hasMany(BillLineItem::class);
    }

    /** @return HasMany<BillPayment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(BillPayment::class);
    }

    /** @return HasMany<BillRefund, $this> */
    public function refunds(): HasMany
    {
        return $this->hasMany(BillRefund::class);
    }

    public function balanceDue(): string
    {
        return bcsub((string) $this->total, (string) $this->amount_paid, 2);
    }

    public function invoiceNumber(): string
    {
        if (! $this->financial_year) {
            return (string) $this->bill_number;
        }

        $prefix = $this->branch?->invoice_prefix ?? 'INV';

        return "{$prefix}/{$this->financial_year}/".str_pad((string) $this->bill_number, 5, '0', STR_PAD_LEFT);
    }

    /** @param Builder<Bill> $query */
    public function scopeUnpaidOrPartial(Builder $query): Builder
    {
        return $query->whereIn('status', [self::StatusUnpaid, self::StatusPartial]);
    }

    /**
     * Groups line items by GST rate, for a receipt's tax summary block.
     *
     * @return array<string, array{taxable: string, cgst: string, sgst: string, igst: string}>
     */
    public function gstBreakdownByRate(): array
    {
        $breakdown = [];

        foreach ($this->lineItems as $item) {
            $rate = (string) $item->tax_rate;
            $taxableAmount = bcsub((string) $item->line_total, (string) $item->discount_amount, 2);

            $breakdown[$rate] ??= ['taxable' => '0', 'cgst' => '0', 'sgst' => '0', 'igst' => '0'];
            $breakdown[$rate]['taxable'] = bcadd($breakdown[$rate]['taxable'], $taxableAmount, 2);
            $breakdown[$rate]['cgst'] = bcadd($breakdown[$rate]['cgst'], (string) $item->cgst_amount, 2);
            $breakdown[$rate]['sgst'] = bcadd($breakdown[$rate]['sgst'], (string) $item->sgst_amount, 2);
            $breakdown[$rate]['igst'] = bcadd($breakdown[$rate]['igst'], (string) $item->igst_amount, 2);
        }

        ksort($breakdown, SORT_NUMERIC);

        return $breakdown;
    }
}
