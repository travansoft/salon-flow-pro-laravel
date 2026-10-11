@extends('layouts.admin')

@section('title', $service->name)

@section('content')
    <div class="sfp-page-header">
        <div>
            <h1 class="sfp-page-title">{{ $service->name }}</h1>
            <p class="sfp-page-subtitle">
                {{ $service->category?->name ?: 'Uncategorised' }}
                @if ($service->code)
                    &middot; Code <span class="sfp-mono">{{ $service->code }}</span>
                @endif
            </p>
        </div>
        @can('services.edit')
            <a href="{{ $tenantUrl->route('services.edit', $service) }}" class="sfp-btn-outline">Edit service</a>
        @endcan
    </div>

    <div class="sfp-card" style="margin-bottom:16px">
        <div style="display:flex;align-items:center;gap:28px;flex-wrap:wrap">
            <div>
                <div class="sfp-label" style="margin-bottom:6px">Price (incl. GST)</div>
                <div class="sfp-mono" style="font-size:20px">&#8377;{{ number_format($service->priceInclusiveOfTax((float) $tenant->default_gst_rate), 2) }}</div>
                <div style="font-size:11.5px;color:#94A19D;margin-top:2px">&#8377;{{ number_format($service->exclusivePriceForDisplay((float) $tenant->default_gst_rate), 2) }} + {{ $service->effectiveTaxRate((float) $tenant->default_gst_rate) }}% GST</div>
            </div>
            <div>
                <div class="sfp-label" style="margin-bottom:6px">Duration</div>
                <div class="sfp-mono" style="font-size:20px">{{ $service->duration_minutes }} min</div>
            </div>
            <div>
                <div class="sfp-label" style="margin-bottom:6px">Status</div>
                @if ($service->is_active)
                    <span class="sfp-pill sfp-pill-green">Active</span>
                @else
                    <span class="sfp-pill sfp-pill-neutral">Disabled</span>
                @endif
            </div>
            @if ($service->requires_rate_confirmation)
                <div>
                    <div class="sfp-label" style="margin-bottom:6px">Rate</div>
                    <span class="sfp-pill sfp-pill-amber">Confirm at billing</span>
                </div>
            @endif
        </div>
    </div>

    @if ($service->is_combo)
        <h2 class="sfp-card-title">Combo services</h2>
        <div class="sfp-table-wrap" style="margin-bottom:16px">
            <div class="sfp-table-head-row" style="grid-template-columns:1fr 1fr">
                <span>Service</span>
                <span>Price (incl. GST)</span>
            </div>
            @foreach ($service->comboItems as $comboItem)
                <div class="sfp-table-row" style="grid-template-columns:1fr 1fr">
                    <span style="font-size:13.5px">{{ $comboItem->component->name }}</span>
                    <span class="sfp-mono" style="font-size:13px">&#8377;{{ number_format($comboItem->price, 2) }}</span>
                </div>
            @endforeach
        </div>
    @endif

    @unless ($service->is_combo)
        <h2 class="sfp-card-title" id="service-products">Products used (estimate per service)</h2>
        <div class="sfp-table-wrap" style="margin-bottom:16px">
            <div class="sfp-table-head-row" style="grid-template-columns:2fr 1.4fr 1fr">
                <span>Product</span>
                <span>Quantity used</span>
                <span></span>
            </div>

            @forelse ($usages as $usage)
                <div class="sfp-table-row" style="grid-template-columns:2fr 1.4fr 1fr">
                    <span style="font-size:14px">{{ $usage->product->name }}</span>
                    @can('services.edit')
                        <form action="{{ $tenantUrl->route('services.products.update', ['service' => $service, 'usage' => $usage]) }}" method="POST" style="display:flex;gap:6px;align-items:center">
                            @csrf
                            @method('PUT')
                            <input type="number" step="0.01" min="0.01" name="quantity_used" class="sfp-input" value="{{ $usage->quantity_used }}" style="width:100px">
                            <span style="font-size:12.5px;color:#66736F">{{ $usage->product->unit }}</span>
                            <button type="submit" class="sfp-btn-outline">Save</button>
                        </form>
                        <form action="{{ $tenantUrl->route('services.products.destroy', ['service' => $service, 'usage' => $usage]) }}" method="POST" style="text-align:right">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="sfp-btn-outline">Remove</button>
                        </form>
                    @else
                        <span class="sfp-mono" style="font-size:13px">{{ number_format($usage->quantity_used, 2) }} {{ $usage->product->unit }}</span>
                        <span></span>
                    @endcan
                </div>
            @empty
                <div class="sfp-table-row" style="grid-template-columns:1fr">
                    <span style="color:#94A19D;font-size:13.5px">No products linked. Stock is not reduced when this service is billed.</span>
                </div>
            @endforelse

            @can('services.edit')
                <div class="sfp-table-row" style="grid-template-columns:1fr">
                    <form action="{{ $tenantUrl->route('services.products.store', $service) }}" method="POST" style="display:flex;gap:8px;align-items:flex-start;flex-wrap:wrap">
                        @csrf
                        <div>
                            <select name="product_id" class="sfp-select" style="min-width:240px">
                                <option value="">Add a product&hellip;</option>
                                @foreach ($availableProducts as $product)
                                    <option value="{{ $product->id }}" @selected(old('product_id') == $product->id)>{{ $product->name }} ({{ $product->unit }})</option>
                                @endforeach
                            </select>
                            @error('product_id')
                                <span class="sfp-invalid-feedback">{{ $message }}</span>
                            @enderror
                        </div>
                        <div>
                            <input type="number" step="0.01" min="0.01" name="quantity_used" class="sfp-input" placeholder="Quantity" value="{{ old('quantity_used') }}" style="width:120px">
                            @error('quantity_used')
                                <span class="sfp-invalid-feedback">{{ $message }}</span>
                            @enderror
                        </div>
                        <button type="submit" class="sfp-btn-primary">Add</button>
                    </form>
                </div>
            @endcan
        </div>
    @endunless

    <h2 class="sfp-card-title">Price history</h2>
    <div class="sfp-table-wrap">
        <div class="sfp-table-head-row" style="grid-template-columns:1fr 1fr 1fr">
            <span>Price (incl. GST)</span>
            <span>Effective from</span>
            <span>Changed by</span>
        </div>
        @forelse ($service->priceHistories()->latest('effective_from')->get() as $history)
            <div class="sfp-table-row" style="grid-template-columns:1fr 1fr 1fr">
                <span class="sfp-mono" style="font-size:13px">&#8377;{{ number_format($history->price, 2) }}</span>
                <span style="font-size:13.5px;color:#66736F">{{ $history->effective_from->format('d M Y, H:i') }}</span>
                <span style="font-size:13.5px;color:#66736F">{{ $history->changedBy?->name ?? '—' }}</span>
            </div>
        @empty
            <div class="sfp-table-row" style="grid-template-columns:1fr">
                <span style="color:#94A19D;font-size:13.5px">No price changes recorded.</span>
            </div>
        @endforelse
    </div>
@endsection
