<?php

namespace App\Models;

use App\Models\Scopes\BranchScope;
use App\Models\Scopes\TenantScope;
use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['tenant_id', 'branch_id', 'name', 'code', 'category_id', 'price', 'requires_rate_confirmation', 'duration_minutes', 'is_active', 'tax_rate', 'hsn_sac_code'])]
#[ScopedBy([TenantScope::class, BranchScope::class])]
class Service extends Model
{
    /** @use HasFactory<ServiceFactory> */
    use HasFactory, SoftDeletes;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_active' => 'boolean',
            'requires_rate_confirmation' => 'boolean',
            'tax_rate' => 'decimal:2',
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

    /** @return BelongsTo<ServiceCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ServiceCategory::class, 'category_id');
    }

    /** @return BelongsToMany<StaffProfile, $this> */
    public function staff(): BelongsToMany
    {
        return $this->belongsToMany(StaffProfile::class, 'staff_service');
    }

    /** @return HasMany<ServicePriceHistory, $this> */
    public function priceHistories(): HasMany
    {
        return $this->hasMany(ServicePriceHistory::class);
    }

    /** @return BelongsToMany<Product, $this> */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'service_product_usages')->withPivot('quantity_used');
    }

    /** @param Builder<Service> $query */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** @param Builder<Service> $query */
    public function scopeWithCode(Builder $query, string $code): Builder
    {
        return $query->where('code', $code);
    }

    /** @param Builder<Service> $query */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        $needle = '%'.mb_strtolower($term).'%';

        return $query->where(function (Builder $query) use ($needle): void {
            $query->whereRaw('LOWER(name) LIKE ?', [$needle])
                ->orWhereRaw('LOWER(code) LIKE ?', [$needle]);
        });
    }

    public function effectiveTaxRate(float $tenantDefaultGstRate): string
    {
        return (string) ($this->tax_rate ?? $tenantDefaultGstRate);
    }

    /**
     * Service::price is stored GST-inclusive, so this is the price itself.
     * Kept as a named accessor so call sites read clearly and don't need to
     * know that price is already tax-inclusive.
     */
    public function priceInclusiveOfTax(float $tenantDefaultGstRate): string
    {
        return (string) $this->price;
    }

    /**
     * Derives the GST-exclusive base price from the stored inclusive price,
     * for display only (e.g. "Base price + GST = inclusive"). Billing never
     * uses this directly — it re-derives the exclusive amount from the
     * inclusive line total to avoid rounding a per-unit price twice.
     */
    public function exclusivePriceForDisplay(float $tenantDefaultGstRate): string
    {
        $rate = $this->effectiveTaxRate($tenantDefaultGstRate);

        return bcdiv((string) $this->price, bcadd('1', bcdiv($rate, '100', 4), 4), 2);
    }
}
