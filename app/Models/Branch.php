<?php

namespace App\Models;

use App\Models\Scopes\TenantScope;
use Database\Factories\BranchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['tenant_id', 'name', 'slug', 'invoice_prefix', 'address', 'phone', 'gst_state_code', 'is_active'])]
#[ScopedBy([TenantScope::class])]
class Branch extends Model
{
    /** @use HasFactory<BranchFactory> */
    use HasFactory, SoftDeletes;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return BelongsToMany<User, $this> */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_branch');
    }

    /** @param Builder<Branch> $query */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Resolves the tenant's default branch, creating it if none exists yet.
     * Bypasses TenantScope since callers (e.g. factories building fixtures
     * for a specific tenant_id) may run before any TenantContext is set.
     */
    public static function defaultForTenant(int $tenantId): self
    {
        return static::withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => $tenantId, 'slug' => 'main'],
            ['name' => 'Main Branch', 'invoice_prefix' => 'INV', 'is_active' => true]
        );
    }
}
