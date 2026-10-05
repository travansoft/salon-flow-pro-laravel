<?php

namespace App\Models;

use App\Enums\BridalDressType;
use App\Enums\BridalVenueType;
use App\Models\Scopes\BranchScope;
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
use Illuminate\Support\Carbon;

#[Fillable([
    'tenant_id', 'branch_id', 'client_id', 'event_name', 'event_date', 'venue', 'venue_type', 'home_location',
    'has_studio_trial', 'trial_date', 'ready_time', 'total_amount', 'advance_amount', 'guest_makeup_count',
    'groom_makeup', 'dress_type', 'saree_drapist_name', 'notes', 'status',
])]
#[ScopedBy([TenantScope::class, BranchScope::class])]
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
            'trial_date' => 'date',
            'venue_type' => BridalVenueType::class,
            'dress_type' => BridalDressType::class,
            'has_studio_trial' => 'boolean',
            'groom_makeup' => 'boolean',
            'total_amount' => 'decimal:2',
            'advance_amount' => 'decimal:2',
        ];
    }

    public function balanceAmount(): string
    {
        return bcsub((string) $this->total_amount, (string) $this->advance_amount, 2);
    }

    public function readyTimeLabel(): ?string
    {
        return $this->ready_time ? Carbon::parse($this->ready_time)->format('h:i A') : null;
    }

    public function billedAmount(): string
    {
        return number_format((float) $this->bills->where('status', '!=', Bill::StatusVoid)->sum('total'), 2, '.', '');
    }

    /** @return HasMany<Bill, $this> */
    public function bills(): HasMany
    {
        return $this->hasMany(Bill::class);
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
