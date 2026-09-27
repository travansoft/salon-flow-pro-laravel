<?php

namespace App\Models;

use App\Models\Scopes\BranchScope;
use App\Models\Scopes\TenantScope;
use Database\Factories\AppointmentReminderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tenant_id', 'branch_id', 'appointment_id', 'type', 'channel', 'scheduled_for', 'sent_at', 'status'])]
#[ScopedBy([TenantScope::class, BranchScope::class])]
class AppointmentReminder extends Model
{
    /** @use HasFactory<AppointmentReminderFactory> */
    use HasFactory;

    public const TypeConfirmation = 'confirmation';

    public const TypeReminder = 'reminder';

    public const StatusPending = 'pending';

    public const StatusSent = 'sent';

    public const StatusFailed = 'failed';

    public const StatusCancelled = 'cancelled';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'scheduled_for' => 'datetime',
            'sent_at' => 'datetime',
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

    /** @return BelongsTo<Appointment, $this> */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    /** @param Builder<AppointmentReminder> $query */
    public function scopeDue(Builder $query): Builder
    {
        return $query->where('status', self::StatusPending)
            ->where('scheduled_for', '<=', now());
    }
}
