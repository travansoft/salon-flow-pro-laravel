@extends('layouts.super-admin')

@section('title', 'Add Tenant')

@section('content')
    <div class="sfp-page-header">
        <div>
            <h1 class="sfp-page-title">Add tenant</h1>
            <p class="sfp-page-subtitle">Create a new salon studio.</p>
        </div>
    </div>

    <div class="sfp-card">
        <form action="{{ $superAdminUrl->route('superAdmin.tenants.store') }}" method="POST" enctype="multipart/form-data">
            @csrf

            <div class="sfp-field">
                <label class="sfp-label">Name *</label>
                <input type="text" name="name" class="sfp-input" value="{{ old('name') }}">
                @error('name')
                    <span class="sfp-invalid-feedback">{{ $message }}</span>
                @enderror
            </div>

            <div class="sfp-split-2">
                <div class="sfp-field">
                    <label class="sfp-label">Slug *</label>
                    <input type="text" name="slug" class="sfp-input" value="{{ old('slug') }}" placeholder="e.g. mejora">
                    @error('slug')
                        <span class="sfp-invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>

                <div class="sfp-field">
                    <label class="sfp-label">Subdomain *</label>
                    <input type="text" name="subdomain" class="sfp-input" value="{{ old('subdomain') }}" placeholder="e.g. mejora">
                    @error('subdomain')
                        <span class="sfp-invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>
            </div>

            <div class="sfp-field">
                <label class="sfp-label">Custom domain</label>
                <input type="text" name="custom_domain" class="sfp-input" value="{{ old('custom_domain') }}" placeholder="Optional">
                @error('custom_domain')
                    <span class="sfp-invalid-feedback">{{ $message }}</span>
                @enderror
            </div>

            <div class="sfp-field form-check">
                <input type="checkbox" name="is_active" value="1" class="form-check-input" @checked(old('is_active', true))>
                <label class="form-check-label sfp-label" style="display: inline; text-transform: none; letter-spacing: normal;">Active</label>
            </div>

            <div class="sfp-card-title" style="margin-top: 24px;">Branding</div>
            <p style="color: #66736F; font-size: 13px; margin-top: -8px;">PNG, JPG or SVG, up to 500KB.</p>

            <div class="sfp-split-2">
                <div class="sfp-field">
                    <label class="sfp-label">Print logo</label>
                    <input type="file" name="print_logo" class="sfp-input" accept=".png,.jpg,.jpeg,.svg">
                    @error('print_logo')
                        <span class="sfp-invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>

                <div class="sfp-field">
                    <label class="sfp-label">UI logo</label>
                    <input type="file" name="ui_logo" class="sfp-input" accept=".png,.jpg,.jpeg,.svg">
                    @error('ui_logo')
                        <span class="sfp-invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>
            </div>

            <div class="sfp-form-actions">
                <button type="submit" class="sfp-btn-primary">Save</button>
            </div>
        </form>
    </div>
@endsection
