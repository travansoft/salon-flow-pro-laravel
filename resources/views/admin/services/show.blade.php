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
        @php
            $editingUsageId = (int) old('usage_id');
            $addHasErrors = ! $editingUsageId && $errors->hasAny(['product_id', 'quantity_used']);
        @endphp

        <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:12px">
            <h2 class="sfp-card-title" id="service-products" style="margin:0">Products used (estimate per service)</h2>
            @can('services.edit')
                <button type="button" class="sfp-btn-outline" data-bs-toggle="modal" data-bs-target="#addServiceProductModal">+ Add product</button>
            @endcan
        </div>

        <div class="sfp-table-wrap" style="margin-bottom:16px">
            <div class="sfp-table-head-row" style="grid-template-columns:2fr 1.4fr 1.4fr">
                <span>Product</span>
                <span>Quantity used</span>
                <span></span>
            </div>

            @forelse ($usages as $usage)
                <div class="sfp-table-row" style="grid-template-columns:2fr 1.4fr 1.4fr">
                    <span style="font-size:14px">{{ $usage->product->name }}</span>
                    <span class="sfp-mono" style="font-size:13px">{{ number_format($usage->quantity_used, 2) }} {{ $usage->product->unit }}</span>
                    <span style="display:flex;gap:6px;justify-content:flex-end">
                        @can('services.edit')
                            <button type="button" class="sfp-btn-outline" data-bs-toggle="modal" data-bs-target="#editServiceProductModal{{ $usage->id }}">Edit</button>
                            <form action="{{ $tenantUrl->route('services.products.destroy', ['service' => $service, 'usage' => $usage]) }}" method="POST" onsubmit="return confirm('Remove this product from the service?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="sfp-btn-outline">Remove</button>
                            </form>
                        @endcan
                    </span>
                </div>
            @empty
                <div class="sfp-table-row" style="grid-template-columns:1fr">
                    <span style="color:#94A19D;font-size:13.5px">No products linked. Stock is not reduced when this service is billed.</span>
                </div>
            @endforelse
        </div>

        @can('services.edit')
            <div class="modal {{ $addHasErrors ? 'show' : '' }}" id="addServiceProductModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form action="{{ $tenantUrl->route('services.products.store', $service) }}" method="POST">
                            @csrf

                            <div class="modal-header">
                                <h5 class="modal-title">Add product</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>

                            <div class="modal-body">
                                <div class="sfp-field">
                                    <label class="sfp-label">Product</label>
                                    <select name="product_id" class="sfp-select">
                                        <option value="">Select a product&hellip;</option>
                                        @foreach ($availableProducts as $product)
                                            <option value="{{ $product->id }}" @selected(old('product_id') == $product->id)>{{ $product->name }} ({{ $product->unit }})</option>
                                        @endforeach
                                    </select>
                                    @error('product_id')
                                        <span class="sfp-invalid-feedback">{{ $message }}</span>
                                    @enderror
                                </div>

                                <div class="sfp-field">
                                    <label class="sfp-label">Quantity used</label>
                                    <input type="number" step="0.01" min="0.01" name="quantity_used" class="sfp-input" value="{{ $addHasErrors ? old('quantity_used') : '' }}">
                                    @if ($addHasErrors)
                                        @error('quantity_used')
                                            <span class="sfp-invalid-feedback">{{ $message }}</span>
                                        @enderror
                                    @endif
                                </div>
                            </div>

                            <div class="modal-footer">
                                <button type="button" class="sfp-btn-outline" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="sfp-btn-primary">Add</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            @foreach ($usages as $usage)
                @php
                    $editHasErrors = $editingUsageId === $usage->id && $errors->has('quantity_used');
                @endphp

                <div class="modal {{ $editHasErrors ? 'show' : '' }}" id="editServiceProductModal{{ $usage->id }}" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <form action="{{ $tenantUrl->route('services.products.update', ['service' => $service, 'usage' => $usage]) }}" method="POST">
                                @csrf
                                @method('PUT')
                                <input type="hidden" name="usage_id" value="{{ $usage->id }}">

                                <div class="modal-header">
                                    <h5 class="modal-title">Edit {{ $usage->product->name }}</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>

                                <div class="modal-body">
                                    <div class="sfp-field">
                                        <label class="sfp-label">Quantity used ({{ $usage->product->unit }})</label>
                                        <input type="number" step="0.01" min="0.01" name="quantity_used" class="sfp-input" value="{{ $editHasErrors ? old('quantity_used') : $usage->quantity_used }}">
                                        @if ($editHasErrors)
                                            @error('quantity_used')
                                                <span class="sfp-invalid-feedback">{{ $message }}</span>
                                            @enderror
                                        @endif
                                    </div>
                                </div>

                                <div class="modal-footer">
                                    <button type="button" class="sfp-btn-outline" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="sfp-btn-primary">Save</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            @endforeach
        @endcan
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
