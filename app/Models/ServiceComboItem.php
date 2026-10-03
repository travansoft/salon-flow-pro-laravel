<?php

namespace App\Models;

use App\Models\Scopes\BranchScope;
use App\Models\Scopes\TenantScope;
use Database\Factories\ServiceComboItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tenant_id', 'branch_id', 'combo_service_id', 'component_service_id', 'price', 'sort_order'])]
#[ScopedBy([TenantScope::class, BranchScope::class])]
class ServiceComboItem extends Model
{
    /** @use HasFactory<ServiceComboItemFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Service, $this> */
    public function combo(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'combo_service_id');
    }

    /** @return BelongsTo<Service, $this> */
    public function component(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'component_service_id');
    }
}
