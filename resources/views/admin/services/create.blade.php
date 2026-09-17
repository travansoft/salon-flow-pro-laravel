@extends('layouts.admin')

@section('title', 'Add Service')

@section('content')
    <div class="sfp-page-header">
        <div>
            <h1 class="sfp-page-title">Add service</h1>
            <p class="sfp-page-subtitle">New services appear in the catalogue and can be booked once saved.</p>
        </div>
    </div>

    <div class="sfp-card">
        <form action="{{ $tenantUrl->route('services.store') }}" method="POST">
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
                    <label class="sfp-label">POS code</label>
                    <input type="text" name="code" class="sfp-input" value="{{ old('code') }}" placeholder="e.g. 101">
                    @error('code')
                        <span class="sfp-invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>
            </div>

            <div class="sfp-field">
                <label class="sfp-label">Category</label>
                <select name="category_id" class="sfp-select">
                    <option value="">No category</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected(old('category_id') == $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
                @error('category_id')
                    <span class="sfp-invalid-feedback">{{ $message }}</span>
                @enderror
                <a href="{{ $tenantUrl->route('serviceCategories.create') }}" class="sfp-action-link" style="font-size:12px">+ Add a new category</a>
            </div>

            <div class="sfp-split-2">
                <div class="sfp-field">
                    <label class="sfp-label">Price</label>
                    <input type="number" step="0.01" min="0" name="price" class="sfp-input" value="{{ old('price') }}">
                    @error('price')
                        <span class="sfp-invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>

                <div class="sfp-field">
                    <label class="sfp-label">Duration (minutes)</label>
                    <input type="number" min="1" name="duration_minutes" class="sfp-input" value="{{ old('duration_minutes') }}">
                    @error('duration_minutes')
                        <span class="sfp-invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>
            </div>

            <div class="sfp-split-2">
                <div class="sfp-field">
                    <label class="sfp-label">GST rate (%)</label>
                    <input type="number" step="0.01" min="0" max="100" name="tax_rate" class="sfp-input" value="{{ old('tax_rate') }}" placeholder="Uses tenant default if blank">
                    @error('tax_rate')
                        <span class="sfp-invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>

                <div class="sfp-field">
                    <label class="sfp-label">HSN/SAC code</label>
                    <input type="text" name="hsn_sac_code" class="sfp-input" value="{{ old('hsn_sac_code') }}">
                    @error('hsn_sac_code')
                        <span class="sfp-invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>
            </div>

            <div class="sfp-card-title" style="margin-top: 24px;">Eligible staff</div>
            <p style="color: #66736F; font-size: 13px; margin-top: -8px;">Select which staff members can perform this service. Only mapped staff can be assigned to it when billing.</p>

            <div class="sfp-field">
                @foreach ($staff as $staffMember)
                    <div class="form-check">
                        <input type="checkbox" name="staff_ids[]" value="{{ $staffMember->id }}" id="staff-{{ $staffMember->id }}" class="form-check-input" @checked(collect(old('staff_ids', []))->contains($staffMember->id))>
                        <label class="form-check-label" for="staff-{{ $staffMember->id }}">{{ $staffMember->name }}</label>
                    </div>
                @endforeach
                @error('staff_ids')
                    <span class="sfp-invalid-feedback">{{ $message }}</span>
                @enderror
            </div>

            <div class="sfp-form-actions">
                <button type="submit" class="sfp-btn-primary">Save</button>
                <a href="{{ $tenantUrl->route('services.index') }}" class="sfp-btn-outline">Cancel</a>
            </div>
        </form>
    </div>
@endsection
