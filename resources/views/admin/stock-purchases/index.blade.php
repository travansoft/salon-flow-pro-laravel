@extends('layouts.admin')

@section('title', 'Stock purchases')

@section('content')
    <div class="sfp-page-header">
        <div>
            <h1 class="sfp-page-title">Stock purchases</h1>
            <p class="sfp-page-subtitle">Every purchase increases the product's stock on hand.</p>
        </div>
        <div class="sfp-row">
            <a href="{{ $tenantUrl->route('products.index') }}" class="sfp-btn-outline">Inventory</a>
            @can('inventory.create')
                <a href="{{ $tenantUrl->route('stockPurchases.create') }}" class="sfp-btn-pill-dark">+ Record purchase</a>
            @endcan
        </div>
    </div>

    <div class="sfp-table-wrap">
        <div class="sfp-table-head-row" style="grid-template-columns:100px 2fr 100px 100px 1.4fr 1fr">
            <span>Date</span>
            <span>Product</span>
            <span style="text-align:right">Quantity</span>
            <span style="text-align:right">Unit cost</span>
            <span>Supplier</span>
            <span>Invoice</span>
        </div>

        @forelse ($purchases as $purchase)
            <div class="sfp-table-row" style="grid-template-columns:100px 2fr 100px 100px 1.4fr 1fr">
                <span style="font-size:13px;color:#66736F">{{ $purchase->purchased_at->format('d M Y') }}</span>
                <span style="font-size:14px">{{ $purchase->product?->name ?? '—' }}</span>
                <span class="sfp-mono" style="font-size:13px;text-align:right">{{ number_format($purchase->quantity, 2) }} {{ $purchase->product?->unit }}</span>
                <span class="sfp-mono" style="font-size:13px;text-align:right">&#8377;{{ number_format($purchase->unit_cost, 2) }}</span>
                <span style="font-size:13px;color:#66736F">{{ $purchase->supplier_name ?: '—' }}</span>
                <span class="sfp-mono" style="font-size:12.5px;color:#66736F">{{ $purchase->invoice_no ?: '—' }}</span>
            </div>
        @empty
            <div class="sfp-table-row" style="grid-template-columns:1fr">
                <p style="color:#66736F;margin:0">No purchases recorded yet.</p>
            </div>
        @endforelse
    </div>
@endsection
