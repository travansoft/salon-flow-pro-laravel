<?php

namespace App\Models;

use App\Models\Scopes\TenantScope;
use Database\Factories\StaffProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'tenant_id', 'user_id', 'name', 'email', 'designation_id', 'photo_path', 'phone', 'is_active',
    'date_of_birth', 'gender', 'address', 'emergency_contact_name', 'emergency_contact_phone',
    'employee_code', 'date_of_joining', 'employment_type', 'reporting_manager_id',
    'base_salary', 'bank_account_number', 'bank_ifsc',
    'government_id_number', 'id_document_path', 'contract_document_path',
])]
#[ScopedBy([TenantScope::class])]
class StaffProfile extends Model
{
    /** @use HasFactory<StaffProfileFactory> */
    use HasFactory, SoftDeletes;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'date_of_birth' => 'date',
            'date_of_joining' => 'date',
            'base_salary' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Designation, $this> */
    public function designation(): BelongsTo
    {
        return $this->belongsTo(Designation::class);
    }

    /** @return BelongsTo<StaffProfile, $this> */
    public function reportingManager(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reporting_manager_id');
    }

    /** @return HasMany<StaffProfile, $this> */
    public function directReports(): HasMany
    {
        return $this->hasMany(self::class, 'reporting_manager_id');
    }

    /** @return BelongsToMany<Service, $this> */
    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'staff_service');
    }

    /** @return HasMany<StaffShift, $this> */
    public function shifts(): HasMany
    {
        return $this->hasMany(StaffShift::class);
    }

    /** @return HasMany<StaffLeaveRequest, $this> */
    public function leaveRequests(): HasMany
    {
        return $this->hasMany(StaffLeaveRequest::class);
    }

    /** @return HasMany<Appointment, $this> */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    /** @return HasMany<StaffTarget, $this> */
    public function targets(): HasMany
    {
        return $this->hasMany(StaffTarget::class);
    }

    /** @return HasMany<StaffIncentive, $this> */
    public function incentives(): HasMany
    {
        return $this->hasMany(StaffIncentive::class);
    }

    /** @return BelongsToMany<BridalEngagement, $this> */
    public function bridalEngagements(): BelongsToMany
    {
        return $this->belongsToMany(BridalEngagement::class, 'bridal_engagement_staff');
    }

    /** @param Builder<StaffProfile> $query */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function hasLogin(): bool
    {
        return $this->user_id !== null;
    }
}
