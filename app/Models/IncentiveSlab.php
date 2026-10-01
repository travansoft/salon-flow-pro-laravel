<?php

namespace App\Models;

use App\Models\Scopes\TenantScope;
use Database\Factories\IncentiveSlabFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tenant_id', 'min_achievement_percent', 'incentive_percent'])]
#[ScopedBy([TenantScope::class])]
class IncentiveSlab extends Model
{
    /** @use HasFactory<IncentiveSlabFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'min_achievement_percent' => 'decimal:2',
            'incentive_percent' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
