@extends('layouts.admin')

@section('title', 'Add Branch')

@section('content')
    <div class="sfp-page-header">
        <div>
            <h1 class="sfp-page-title">Add branch</h1>
            <p class="sfp-page-subtitle">New branches get their own catalogue, staff assignments, and invoice sequence.</p>
        </div>
    </div>

    <div class="sfp-card">
        <form action="{{ $tenantUrl->route('branches.store') }}" method="POST">
            @csrf

            <div class="sfp-split-2">
                <div class="sfp-field">
                    <label class="sfp-label">Name</label>
                    <input type="text" name="name" class="sfp-input" value="{{ old('name') }}">
                    @error('name')
                        <span class="sfp-invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>

                <div class="sfp-field">
                    <label class="sfp-label">Slug</label>
                    <input type="text" name="slug" class="sfp-input" value="{{ old('slug') }}" placeholder="e.g. marine-drive">
                    @error('slug')
                        <span class="sfp-invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>
            </div>

            <div class="sfp-field">
                <label class="sfp-label">Invoice prefix</label>
                <input type="text" name="invoice_prefix" class="sfp-input" value="{{ old('invoice_prefix') }}" placeholder="e.g. HSR" style="text-transform:uppercase">
                <p style="font-size:12.5px;color:#66736F;margin:6px 0 0">Used in this branch's invoice numbers, e.g. HSR/2026-27/00001.</p>
                @error('invoice_prefix')
                    <span class="sfp-invalid-feedback">{{ $message }}</span>
                @enderror
            </div>

            <div class="sfp-split-2">
                <div class="sfp-field">
                    <label class="sfp-label">Address</label>
                    <input type="text" name="address" class="sfp-input" value="{{ old('address') }}">
                    @error('address')
                        <span class="sfp-invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>

                <div class="sfp-field">
                    <label class="sfp-label">Phone</label>
                    <input type="text" name="phone" class="sfp-input" value="{{ old('phone') }}">
                    @error('phone')
                        <span class="sfp-invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>
            </div>

            <div class="sfp-field">
                <label class="sfp-label">GST state code</label>
                <input type="text" name="gst_state_code" class="sfp-input" value="{{ old('gst_state_code') }}" placeholder="Uses tenant default if blank" maxlength="2" style="max-width:120px">
                @error('gst_state_code')
                    <span class="sfp-invalid-feedback">{{ $message }}</span>
                @enderror
            </div>

            <div class="sfp-form-actions">
                <button type="submit" class="sfp-btn-primary">Save</button>
                <a href="{{ $tenantUrl->route('branches.index') }}" class="sfp-btn-outline">Cancel</a>
            </div>
        </form>
    </div>
@endsection
