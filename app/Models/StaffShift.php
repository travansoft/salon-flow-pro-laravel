<?php

namespace App\Models;

use App\Models\Scopes\BranchScope;
use App\Models\Scopes\TenantScope;
use Database\Factories\StaffShiftFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tenant_id', 'branch_id', 'staff_profile_id', 'day_of_week', 'override_date', 'start_time', 'end_time', 'is_working'])]
#[ScopedBy([TenantScope::class, BranchScope::class])]
class StaffShift extends Model
{
    /** @use HasFactory<StaffShiftFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'override_date' => 'date',
            'is_working' => 'boolean',
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

    /** @param Builder<StaffShift> $query */
    public function scopeRecurring(Builder $query): Builder
    {
        return $query->whereNull('override_date');
    }

    /** @param Builder<StaffShift> $query */
    public function scopeOverrides(Builder $query): Builder
    {
        return $query->whereNotNull('override_date');
    }
}
