<?php

namespace App\Models;

use App\Models\Scopes\TenantScope;
use Database\Factories\BillPaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tenant_id', 'bill_id', 'method', 'amount', 'received_by'])]
#[ScopedBy([TenantScope::class])]
class BillPayment extends Model
{
    /** @use HasFactory<BillPaymentFactory> */
    use HasFactory;

    public const MethodCash = 'cash';

    public const MethodCard = 'card';

    public const MethodUpi = 'upi';

    public const MethodBankTransfer = 'bank_transfer';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return BelongsTo<Bill, $this> */
    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }

    /** @return BelongsTo<User, $this> */
    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}
