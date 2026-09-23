<?php

namespace App\Models;

use App\Models\Scopes\TenantScope;
use Database\Factories\WalkInFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tenant_id', 'branch_id', 'client_id', 'name', 'phone', 'service_id', 'assigned_staff_profile_id', 'appointment_id', 'status', 'joined_at'])]
#[ScopedBy([TenantScope::class])]
class WalkIn extends Model
{
    /** @use HasFactory<WalkInFactory> */
    use HasFactory;

    public const StatusWaiting = 'waiting';

    public const StatusAssigned = 'assigned';

    public const StatusCompleted = 'completed';

    public const StatusLeft = 'left';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'joined_at' => 'datetime',
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

    /** @return BelongsTo<Service, $this> */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /** @return BelongsTo<StaffProfile, $this> */
    public function assignedStaffProfile(): BelongsTo
    {
        return $this->belongsTo(StaffProfile::class, 'assigned_staff_profile_id');
    }

    /** @return BelongsTo<Appointment, $this> */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    /** @param Builder<WalkIn> $query */
    public function scopeWaiting(Builder $query): Builder
    {
        return $query->where('status', self::StatusWaiting)->oldest('joined_at');
    }
}
