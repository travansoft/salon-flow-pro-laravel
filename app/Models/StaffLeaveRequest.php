<?php

namespace App\Models;

use App\Models\Scopes\TenantScope;
use Database\Factories\StaffLeaveRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tenant_id', 'branch_id', 'staff_profile_id', 'start_date', 'end_date', 'reason', 'status', 'decided_by', 'decided_at', 'decision_note'])]
#[ScopedBy([TenantScope::class])]
class StaffLeaveRequest extends Model
{
    /** @use HasFactory<StaffLeaveRequestFactory> */
    use HasFactory;

    public const StatusPending = 'pending';

    public const StatusApproved = 'approved';

    public const StatusRejected = 'rejected';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'decided_at' => 'datetime',
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

    /** @return BelongsTo<StaffProfile, $this> */
    public function staffProfile(): BelongsTo
    {
        return $this->belongsTo(StaffProfile::class);
    }

    /** @return BelongsTo<User, $this> */
    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    /** @param Builder<StaffLeaveRequest> $query */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::StatusPending);
    }

    /** @param Builder<StaffLeaveRequest> $query */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', self::StatusApproved);
    }
}
