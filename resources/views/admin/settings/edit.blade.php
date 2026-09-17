@extends('layouts.admin')

@section('title', 'GST Settings')

@section('content')
    <div class="sfp-page-header">
        <div>
            <h1 class="sfp-page-title">GST settings</h1>
            <p class="sfp-page-subtitle">Registered business details used on GST-compliant invoices.</p>
        </div>
    </div>

    <div class="sfp-card">
        <form action="{{ $tenantUrl->route('settings.update') }}" method="POST" enctype="multipart/form-data">
            @csrf
            @method('PUT')

            <div class="sfp-field">
                <label class="sfp-label">Legal business name</label>
                <input type="text" name="legal_name" class="sfp-input" value="{{ old('legal_name', $tenant->legal_name) }}" placeholder="{{ $tenant->name }}">
                @error('legal_name')
                    <span class="sfp-invalid-feedback">{{ $message }}</span>
                @enderror
            </div>

            <div class="sfp-field">
                <label class="sfp-label">Registered address</label>
                <textarea name="address" class="sfp-textarea">{{ old('address', $tenant->address) }}</textarea>
                @error('address')
                    <span class="sfp-invalid-feedback">{{ $message }}</span>
                @enderror
            </div>

            <div class="sfp-split-2">
                <div class="sfp-field">
                    <label class="sfp-label">GSTIN</label>
                    <input type="text" name="gst_number" class="sfp-input" value="{{ old('gst_number', $tenant->gst_number) }}" placeholder="e.g. 32AAAAA0000A1Z5">
                    @error('gst_number')
                        <span class="sfp-invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>

                <div class="sfp-field">
                    <label class="sfp-label">State code</label>
                    <input type="text" name="gst_state_code" class="sfp-input" maxlength="2" value="{{ old('gst_state_code', $tenant->gst_state_code) }}" placeholder="e.g. 32">
                    @error('gst_state_code')
                        <span class="sfp-invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>
            </div>

            <div class="sfp-field">
                <label class="sfp-label">Default GST rate (%)</label>
                <input type="number" step="0.01" min="0" max="100" name="default_gst_rate" class="sfp-input" value="{{ old('default_gst_rate', $tenant->default_gst_rate) }}">
                <p style="font-size:12.5px;color:#66736F;margin:6px 0 0">Used for services that don't have their own GST rate set.</p>
                @error('default_gst_rate')
                    <span class="sfp-invalid-feedback">{{ $message }}</span>
                @enderror
            </div>

            <div class="sfp-card-title" style="margin-top: 24px;">Branding</div>
            <p style="color: #66736F; font-size: 13px; margin-top: -8px;">PNG, JPG or SVG, up to 500KB.</p>

            <div class="sfp-split-2">
                <div class="sfp-field">
                    <label class="sfp-label">Print logo</label>
                    <p style="font-size:12.5px;color:#66736F;margin:0 0 8px">Shown on thermal receipts.</p>
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
                    <p style="font-size:12.5px;color:#66736F;margin:0 0 8px">Shown in the admin sidebar.</p>
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
