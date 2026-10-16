<?php

namespace App\Models;

use Database\Factories\RoleFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    /** @use HasFactory<RoleFactory> */
    use HasFactory;

    /** @var array<int, string> */
    public const BuiltInNames = ['Owner', 'Manager', 'FrontDesk', 'Stylist', 'Director'];

    protected $fillable = ['name', 'guard_name', 'tenant_id', 'description'];

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function isSystem(): bool
    {
        return $this->tenant_id === null;
    }

    public function isBuiltIn(): bool
    {
        return $this->isSystem() && in_array($this->name, self::BuiltInNames, true);
    }

    /** @param Builder<Role> $query */
    public function scopeSystem(Builder $query): Builder
    {
        return $query->whereNull('tenant_id');
    }

    /** @param Builder<Role> $query */
    public function scopeOwnedByTenant(Builder $query, int $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    /** @param Builder<Role> $query */
    public function scopeAssignableByTenant(Builder $query, int $tenantId): Builder
    {
        return $query->where(function (Builder $query) use ($tenantId): void {
            $query->whereNull('tenant_id')->orWhere('tenant_id', $tenantId);
        });
    }
}
