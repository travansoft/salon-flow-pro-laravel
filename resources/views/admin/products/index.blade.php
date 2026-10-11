@extends('layouts.admin')

@section('title', 'Inventory')

@section('content')
    @php
        $lowStockCount = $products->filter(fn ($product) => $product->quantity_on_hand <= $product->reorder_level)->count();
    @endphp

    <div class="sfp-page-header">
        <div>
            <h1 class="sfp-page-title">Inventory</h1>
            <p class="sfp-page-subtitle">
                {{ $products->count() }} items tracked &middot; {{ $lowStockCount }} below reorder level &middot; stock deducts as services complete
            </p>
        </div>
        <div class="sfp-row">
            @can('inventory.view')
                <a href="{{ $tenantUrl->route('lowStockReport.index') }}" class="sfp-btn-outline">Low stock report</a>
                <a href="{{ $tenantUrl->route('productCategories.index') }}" class="sfp-btn-outline">Manage categories</a>
            @endcan
            @can('inventory.create')
                <a href="{{ $tenantUrl->route('products.create') }}" class="sfp-btn-pill-dark">+ Add product</a>
            @endcan
        </div>
    </div>

    <div class="sfp-table-wrap">
        <div class="sfp-table-head-row" style="grid-template-columns:2fr 1fr 100px 100px 1.4fr 90px">
            <span>Product</span>
            <span>Category</span>
            <span style="text-align:right">On hand</span>
            <span style="text-align:right">Reorder at</span>
            <span>Used per service</span>
            <span></span>
        </div>

        @forelse ($products as $product)
            @php
                $isLowStock = $product->quantity_on_hand <= $product->reorder_level;
            @endphp
            <div class="sfp-table-row" style="grid-template-columns:2fr 1fr 100px 100px 1.4fr 90px">
                <div>
                    <div style="font-size:14.5px">
                        <span class="sfp-stock-dot {{ $isLowStock ? 'sfp-stock-dot-low' : '' }}"></span>{{ $product->name }}
                    </div>
                    <div class="sfp-mono" style="font-size:11.5px;color:#94A19D">{{ $product->sku ?? '—' }}</div>
                </div>
                <span style="font-size:13.5px;color:#66736F">{{ $product->category?->name ?: 'Uncategorised' }}</span>
                <span class="sfp-mono" style="font-size:13px;text-align:right">{{ number_format($product->quantity_on_hand, 2) }} {{ $product->unit }}</span>
                <span class="sfp-mono" style="font-size:13px;text-align:right">{{ number_format($product->reorder_level, 2) }} {{ $product->unit }}</span>
                <span style="font-size:12.5px;color:#66736F">{{ $product->services->pluck('name')->join(', ') ?: '—' }}</span>
                <div style="display:flex;align-items:center;justify-content:flex-end;gap:12px">
                    <a href="{{ $tenantUrl->route('products.show', $product) }}" style="font-size:12.5px;color:#1B4B8F">Adjust</a>
                </div>
            </div>
        @empty
            <div class="sfp-table-row" style="grid-template-columns:1fr">
                <p style="color:#66736F;margin:0">No products yet.</p>
            </div>
        @endforelse
    </div>
@endsection
