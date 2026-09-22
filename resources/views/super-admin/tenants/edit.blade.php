@extends('layouts.super-admin')

@section('title', 'Edit Tenant')

@section('content')
    <div class="sfp-page-header">
        <div>
            <h1 class="sfp-page-title">Edit tenant</h1>
            <p class="sfp-page-subtitle">{{ $tenant->name }}</p>
        </div>
    </div>

    <div class="sfp-card">
        <form action="{{ $superAdminUrl->route('superAdmin.tenants.update', $tenant) }}" method="POST" enctype="multipart/form-data">
            @csrf
            @method('PUT')

            <div class="sfp-field">
                <label class="sfp-label">Name *</label>
                <input type="text" name="name" class="sfp-input" value="{{ old('name', $tenant->name) }}">
                @error('name')
                    <span class="sfp-invalid-feedback">{{ $message }}</span>
                @enderror
            </div>

            <div class="sfp-split-2">
                <div class="sfp-field">
                    <label class="sfp-label">Slug</label>
                    <input type="text" class="sfp-input" value="{{ $tenant->slug }}" disabled>
                </div>

                <div class="sfp-field">
                    <label class="sfp-label">Subdomain</label>
                    <input type="text" class="sfp-input" value="{{ $tenant->subdomain }}" disabled>
                </div>
            </div>

            <div class="sfp-field">
                <label class="sfp-label">Custom domain</label>
                <input type="text" name="custom_domain" class="sfp-input" value="{{ old('custom_domain', $tenant->custom_domain) }}" placeholder="Optional">
                @error('custom_domain')
                    <span class="sfp-invalid-feedback">{{ $message }}</span>
                @enderror
            </div>

            <div class="sfp-field form-check">
                <input type="checkbox" name="is_active" value="1" class="form-check-input" @checked(old('is_active', $tenant->is_active))>
                <label class="form-check-label sfp-label" style="display: inline; text-transform: none; letter-spacing: normal;">Active</label>
            </div>

            <div class="sfp-card-title" style="margin-top: 24px;">Branding</div>
            <p style="color: #66736F; font-size: 13px; margin-top: -8px;">PNG, JPG or SVG, up to 500KB.</p>

            <div class="sfp-split-2">
                <div class="sfp-field">
                    <label class="sfp-label">Print logo</label>
                    @if ($tenant->print_logo)
                        <div style="margin-bottom:10px">
                            <img src="{{ $tenant->print_logo }}" alt="Print logo" style="max-height:60px;max-width:100%">
                        </div>
                        <label style="display:flex;align-items:center;gap:8px;font-size:12.5px;color:#66736F;margin-bottom:8px">
                            <input type="checkbox" name="remove_print_logo" value="1"> Remove current logo
                        </label>
                    @endif
                    <input type="file" name="print_logo" class="sfp-input" accept=".png,.jpg,.jpeg,.svg">
                    @error('print_logo')
                        <span class="sfp-invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>

                <div class="sfp-field">
                    <label class="sfp-label">UI logo</label>
                    @if ($tenant->ui_logo)
                        <div style="margin-bottom:10px">
                            <img src="{{ $tenant->ui_logo }}" alt="UI logo" style="max-height:60px;max-width:100%">
                        </div>
                        <label style="display:flex;align-items:center;gap:8px;font-size:12.5px;color:#66736F;margin-bottom:8px">
                            <input type="checkbox" name="remove_ui_logo" value="1"> Remove current logo
                        </label>
                    @endif
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
