<?php

namespace App\Models;

use App\Models\Scopes\TenantScope;
use Database\Factories\IncentiveSettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tenant_id', 'servicing_share_percent', 'referring_share_percent'])]
#[ScopedBy([TenantScope::class])]
class IncentiveSetting extends Model
{
    /** @use HasFactory<IncentiveSettingFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'servicing_share_percent' => 'decimal:2',
            'referring_share_percent' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
