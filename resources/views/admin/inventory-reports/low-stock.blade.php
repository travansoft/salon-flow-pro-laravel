@extends('layouts.admin')

@section('title', 'Low stock report')

@section('content')
    <div class="sfp-page-header">
        <div>
            <h1 class="sfp-page-title">Low stock report</h1>
            <p class="sfp-page-subtitle">
                {{ $products->count() }} products at or below their threshold. Stock figures are estimates from billing &mdash; check the shelf, enter the count, then order if needed.
            </p>
        </div>
        <div class="sfp-row">
            <a href="{{ $tenantUrl->route('products.index') }}" class="sfp-btn-outline">Inventory</a>
        </div>
    </div>

    <form method="GET" class="sfp-row" style="margin-bottom:16px">
        <select name="category" class="sfp-select" style="max-width:240px" onchange="this.form.submit()">
            <option value="">All categories</option>
            @foreach ($categories as $category)
                <option value="{{ $category->id }}" @selected($categoryId === $category->id)>{{ $category->name }}</option>
            @endforeach
        </select>
    </form>

    <div class="sfp-table-wrap">
        <div class="sfp-table-head-row" style="grid-template-columns:1.8fr 1fr 110px 110px 110px 1.2fr 230px">
            <span>Product</span>
            <span>Category</span>
            <span style="text-align:right">System stock</span>
            <span style="text-align:right">Threshold</span>
            <span style="text-align:right">Suggested order</span>
            <span>Last counted</span>
            <span>Physical count</span>
        </div>

        @forelse ($products as $product)
            @php
                $stats = $countStats[$product->id] ?? ['last_counted_at' => null, 'usage_since_count' => '0.00'];
                $suggested = max(0, $product->reorder_level * 2 - $product->quantity_on_hand);
            @endphp
            <div class="sfp-table-row" style="grid-template-columns:1.8fr 1fr 110px 110px 110px 1.2fr 230px">
                <div>
                    <a href="{{ $tenantUrl->route('products.show', $product) }}" style="font-size:14.5px">
                        <span class="sfp-stock-dot sfp-stock-dot-low"></span>{{ $product->name }}
                    </a>
                    @if ($product->quantity_on_hand < 0)
                        <div style="font-size:11.5px;color:#B4552D">Estimate below zero &mdash; needs a count</div>
                    @endif
                </div>
                <span style="font-size:13.5px;color:#66736F">{{ $product->category?->name ?: 'Uncategorised' }}</span>
                <span class="sfp-mono" style="font-size:13px;text-align:right">{{ number_format($product->quantity_on_hand, 2) }} {{ $product->unit }}</span>
                <span class="sfp-mono" style="font-size:13px;text-align:right">{{ number_format($product->reorder_level, 2) }} {{ $product->unit }}</span>
                <span class="sfp-mono" style="font-size:13px;text-align:right">{{ number_format($suggested, 2) }} {{ $product->unit }}</span>
                <div style="font-size:12.5px;color:#66736F">
                    {{ $stats['last_counted_at'] ? \Illuminate\Support\Carbon::parse($stats['last_counted_at'])->format('d M Y') : 'Never' }}
                    <div class="sfp-mono" style="font-size:11.5px;color:#94A19D">{{ number_format((float) $stats['usage_since_count'], 2) }} est. used since</div>
                </div>
                <div>
                    @can('inventory.edit')
                        <form action="{{ $tenantUrl->route('products.stockCounts.store', $product) }}" method="POST" style="display:flex;gap:6px">
                            @csrf
                            <input type="number" step="0.01" min="0" name="counted_quantity" class="sfp-input" placeholder="Counted" style="width:100px">
                            <button type="submit" class="sfp-btn-outline">Save</button>
                        </form>
                    @endcan
                    @can('inventory.create')
                        <a href="{{ $tenantUrl->route('stockPurchases.create') }}?product={{ $product->id }}" style="font-size:12.5px;color:#1B4B8F">Record purchase</a>
                    @endcan
                </div>
            </div>
        @empty
            <div class="sfp-table-row" style="grid-template-columns:1fr">
                <p style="color:#66736F;margin:0">Nothing is below its threshold.</p>
            </div>
        @endforelse
    </div>
@endsection
