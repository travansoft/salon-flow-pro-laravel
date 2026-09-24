@extends('layouts.admin')

@section('title', 'Edit Branch')

@section('content')
    <div class="sfp-page-header">
        <div>
            <h1 class="sfp-page-title">Edit {{ $branch->name }}</h1>
        </div>
    </div>

    <div class="sfp-card">
        <form action="{{ $tenantUrl->route('branches.update', $branch) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="sfp-split-2">
                <div class="sfp-field">
                    <label class="sfp-label">Name</label>
                    <input type="text" name="name" class="sfp-input" value="{{ old('name', $branch->name) }}">
                    @error('name')
                        <span class="sfp-invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>

                <div class="sfp-field">
                    <label class="sfp-label">Slug</label>
                    <input type="text" name="slug" class="sfp-input" value="{{ old('slug', $branch->slug) }}">
                    @error('slug')
                        <span class="sfp-invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>
            </div>

            <div class="sfp-field">
                <label class="sfp-label">Invoice prefix</label>
                <input type="text" name="invoice_prefix" class="sfp-input" value="{{ old('invoice_prefix', $branch->invoice_prefix) }}" style="text-transform:uppercase">
                <p style="font-size:12.5px;color:#66736F;margin:6px 0 0">Changing this only affects invoices issued after saving.</p>
                @error('invoice_prefix')
                    <span class="sfp-invalid-feedback">{{ $message }}</span>
                @enderror
            </div>

            <div class="sfp-split-2">
                <div class="sfp-field">
                    <label class="sfp-label">Address</label>
                    <input type="text" name="address" class="sfp-input" value="{{ old('address', $branch->address) }}">
                    @error('address')
                        <span class="sfp-invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>

                <div class="sfp-field">
                    <label class="sfp-label">Phone</label>
                    <input type="text" name="phone" class="sfp-input" value="{{ old('phone', $branch->phone) }}">
                    @error('phone')
                        <span class="sfp-invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>
            </div>

            <div class="sfp-field">
                <label class="sfp-label">GST state code</label>
                <input type="text" name="gst_state_code" class="sfp-input" value="{{ old('gst_state_code', $branch->gst_state_code) }}" maxlength="2" style="max-width:120px">
                @error('gst_state_code')
                    <span class="sfp-invalid-feedback">{{ $message }}</span>
                @enderror
            </div>

            <div class="sfp-field" style="display:flex;align-items:center;gap:10px;margin-bottom:18px">
                <input type="checkbox" name="is_active" value="1" id="is_active" @checked(old('is_active', $branch->is_active))>
                <label class="sfp-label" for="is_active" style="margin-bottom:0">Active</label>
                @error('is_active')
                    <span class="sfp-invalid-feedback">{{ $message }}</span>
                @enderror
            </div>

            <div class="sfp-form-actions">
                <button type="submit" class="sfp-btn-primary">Save</button>
                <a href="{{ $tenantUrl->route('branches.show', $branch) }}" class="sfp-btn-outline">Cancel</a>
            </div>
        </form>

        @can('branches.delete')
            <form action="{{ $tenantUrl->route('branches.destroy', $branch) }}" method="POST" style="margin-top:16px">
                @csrf
                @method('DELETE')
                <button type="submit" class="sfp-btn-link-danger">Disable branch</button>
            </form>
        @endcan
    </div>
@endsection
