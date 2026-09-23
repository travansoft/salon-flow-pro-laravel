<?php

namespace App\Models;

use App\Models\Scopes\TenantScope;
use Database\Factories\BridalEngagementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['tenant_id', 'branch_id', 'client_id', 'event_date', 'venue', 'notes', 'status'])]
#[ScopedBy([TenantScope::class])]
class BridalEngagement extends Model
{
    /** @use HasFactory<BridalEngagementFactory> */
    use HasFactory, SoftDeletes;

    public const RoleTrial = 'trial';

    public const RoleEventDay = 'event_day';

    public const StatusPlanned = 'planned';

    public const StatusTrialCompleted = 'trial_completed';

    public const StatusCompleted = 'completed';

    public const StatusCancelled = 'cancelled';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'event_date' => 'date',
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

    /** @return HasMany<Appointment, $this> */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function trialAppointment(): ?Appointment
    {
        return $this->appointments->firstWhere('engagement_role', self::RoleTrial);
    }

    public function eventDayAppointment(): ?Appointment
    {
        return $this->appointments->firstWhere('engagement_role', self::RoleEventDay);
    }

    /** @return BelongsToMany<StaffProfile, $this> */
    public function travelingStaff(): BelongsToMany
    {
        return $this->belongsToMany(StaffProfile::class, 'bridal_engagement_staff');
    }
}
