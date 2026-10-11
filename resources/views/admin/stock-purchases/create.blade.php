@extends('layouts.admin')

@section('title', 'Record purchase')

@section('content')
    <div class="sfp-page-header">
        <div>
            <h1 class="sfp-page-title">Record purchase</h1>
            <p class="sfp-page-subtitle">Stock on hand increases by the quantity received.</p>
        </div>
    </div>

    <div class="sfp-card">
        <form action="{{ $tenantUrl->route('stockPurchases.store') }}" method="POST">
            @csrf

            <div class="sfp-field">
                <label class="sfp-label">Product</label>
                <select name="product_id" class="sfp-select">
                    <option value="">Select a product</option>
                    @foreach ($products as $product)
                        <option value="{{ $product->id }}" @selected(old('product_id', $selectedProductId) == $product->id)>{{ $product->name }} ({{ $product->unit }})</option>
                    @endforeach
                </select>
                @error('product_id')
                    <span class="sfp-invalid-feedback">{{ $message }}</span>
                @enderror
            </div>

            <div class="sfp-split-2">
                <div class="sfp-field">
                    <label class="sfp-label">Quantity received</label>
                    <input type="number" step="0.01" min="0" name="quantity" class="sfp-input" value="{{ old('quantity') }}">
                    @error('quantity')
                        <span class="sfp-invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>

                <div class="sfp-field">
                    <label class="sfp-label">Unit cost (&#8377;)</label>
                    <input type="number" step="0.01" min="0" name="unit_cost" class="sfp-input" value="{{ old('unit_cost') }}">
                    @error('unit_cost')
                        <span class="sfp-invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>
            </div>

            <div class="sfp-split-2">
                <div class="sfp-field">
                    <label class="sfp-label">Supplier</label>
                    <input type="text" name="supplier_name" class="sfp-input" value="{{ old('supplier_name') }}">
                    @error('supplier_name')
                        <span class="sfp-invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>

                <div class="sfp-field">
                    <label class="sfp-label">Invoice no.</label>
                    <input type="text" name="invoice_no" class="sfp-input" value="{{ old('invoice_no') }}">
                    @error('invoice_no')
                        <span class="sfp-invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>
            </div>

            <div class="sfp-field">
                <label class="sfp-label">Purchase date</label>
                <input type="date" name="purchased_at" class="sfp-input" max="{{ now()->toDateString() }}" value="{{ old('purchased_at', now()->toDateString()) }}">
                @error('purchased_at')
                    <span class="sfp-invalid-feedback">{{ $message }}</span>
                @enderror
            </div>

            <div class="sfp-form-actions">
                <button type="submit" class="sfp-btn-primary">Save purchase</button>
                <a href="{{ $tenantUrl->route('stockPurchases.index') }}" class="sfp-btn-outline">Cancel</a>
            </div>
        </form>
    </div>
@endsection
