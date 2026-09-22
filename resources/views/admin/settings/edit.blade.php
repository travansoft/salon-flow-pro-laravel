@extends('layouts.admin')

@section('title', 'Settings')

@php
    $items = [
        [
            'key' => 'legal_name',
            'label' => 'Legal business name',
            'value' => $tenant->legal_name,
            'placeholder' => $tenant->name,
        ],
        [
            'key' => 'address',
            'label' => 'Registered address',
            'value' => $tenant->address,
        ],
        [
            'key' => 'phone',
            'label' => 'Phone',
            'value' => $tenant->phone,
        ],
        [
            'key' => 'gst_number',
            'label' => 'GSTIN',
            'value' => $tenant->gst_number,
        ],
        [
            'key' => 'gst_state_code',
            'label' => 'GST state code',
            'value' => $tenant->gst_state_code,
        ],
        [
            'key' => 'default_gst_rate',
            'label' => 'Default GST rate (%)',
            'value' => $tenant->default_gst_rate,
        ],
        [
            'key' => 'print_logo',
            'label' => 'Print logo',
            'value' => $tenant->print_logo,
            'type' => 'logo',
        ],
        [
            'key' => 'ui_logo',
            'label' => 'UI logo',
            'value' => $tenant->ui_logo,
            'type' => 'logo',
        ],
    ];
@endphp

@section('content')
    <div class="sfp-page-header">
        <div>
            <h1 class="sfp-page-title">Settings</h1>
            <p class="sfp-page-subtitle">Business, GST and branding details used across the admin panel and invoices.</p>
        </div>
    </div>

    @if (session('status'))
        <div class="sfp-alert sfp-alert-success" style="margin-bottom:16px">{{ session('status') }}</div>
    @endif

    <div class="sfp-table-wrap">
        <div class="sfp-table-head-row" style="grid-template-columns:1fr 2fr 100px">
            <span>Setting</span>
            <span>Value</span>
            <span></span>
        </div>

        @foreach ($items as $item)
            <div class="sfp-table-row" style="grid-template-columns:1fr 2fr 100px">
                <span style="font-size:14.5px">{{ $item['label'] }}</span>
                <span style="font-size:14.5px;color:#3A423F">
                    @if (($item['type'] ?? null) === 'logo')
                        @if ($item['value'])
                            <img src="{{ $item['value'] }}" alt="{{ $item['label'] }}" style="max-height:36px;max-width:140px">
                        @else
                            <span style="color:#66736F">Not set</span>
                        @endif
                    @else
                        {{ $item['value'] !== null && $item['value'] !== '' ? $item['value'] : ($item['placeholder'] ?? '—') }}
                    @endif
                </span>
                <div style="display:flex;align-items:center;justify-content:flex-end">
                    @can('settings.edit')
                        <button type="button" class="sfp-action-link" style="background:none;border:none;cursor:pointer" data-bs-toggle="modal" data-bs-target="#editSettingModal-{{ $item['key'] }}">Edit</button>
                    @endcan
                </div>
            </div>
        @endforeach
    </div>

    @foreach ($items as $item)
        @can('settings.edit')
            <div class="modal fade" id="editSettingModal-{{ $item['key'] }}" tabindex="-1" aria-labelledby="editSettingModalLabel-{{ $item['key'] }}" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form action="{{ $tenantUrl->route('settings.update') }}" method="POST" enctype="multipart/form-data">
                            @csrf
                            @method('PUT')

                            @foreach ($items as $hidden)
                                @continue($hidden['key'] === $item['key'])
                                @continue(($hidden['type'] ?? null) === 'logo')
                                <input type="hidden" name="{{ $hidden['key'] }}" value="{{ old($hidden['key'], $hidden['value']) }}">
                            @endforeach

                            <div class="modal-header">
                                <h5 class="modal-title" id="editSettingModalLabel-{{ $item['key'] }}">Edit {{ strtolower($item['label']) }}</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>

                            <div class="modal-body">
                                @if (($item['type'] ?? null) === 'logo')
                                    <p style="font-size:12.5px;color:#66736F;margin:0 0 10px">PNG, JPG or SVG, up to 500KB.</p>

                                    @if ($item['value'])
                                        <div style="margin-bottom:10px">
                                            <img src="{{ $item['value'] }}" alt="{{ $item['label'] }}" style="max-height:60px;max-width:100%">
                                        </div>
                                        <label style="display:flex;align-items:center;gap:8px;font-size:12.5px;color:#66736F;margin-bottom:10px">
                                            <input type="checkbox" name="remove_{{ $item['key'] }}" value="1"> Remove current logo
                                        </label>
                                    @endif

                                    <input type="file" name="{{ $item['key'] }}" class="sfp-input" accept=".png,.jpg,.jpeg,.svg">
                                    @error($item['key'])
                                        <span class="sfp-invalid-feedback">{{ $message }}</span>
                                    @enderror
                                @elseif ($item['key'] === 'address')
                                    <div class="sfp-field">
                                        <textarea name="{{ $item['key'] }}" class="sfp-textarea">{{ old($item['key'], $item['value']) }}</textarea>
                                        @error($item['key'])
                                            <span class="sfp-invalid-feedback">{{ $message }}</span>
                                        @enderror
                                    </div>
                                @elseif ($item['key'] === 'default_gst_rate')
                                    <div class="sfp-field">
                                        <input type="number" step="0.01" min="0" max="100" name="{{ $item['key'] }}" class="sfp-input" value="{{ old($item['key'], $item['value']) }}">
                                        <p style="font-size:12.5px;color:#66736F;margin:6px 0 0">Used for services that don't have their own GST rate set.</p>
                                        @error($item['key'])
                                            <span class="sfp-invalid-feedback">{{ $message }}</span>
                                        @enderror
                                    </div>
                                @elseif ($item['key'] === 'gst_state_code')
                                    <div class="sfp-field">
                                        <input type="text" name="{{ $item['key'] }}" class="sfp-input" maxlength="2" value="{{ old($item['key'], $item['value']) }}" placeholder="e.g. 32">
                                        @error($item['key'])
                                            <span class="sfp-invalid-feedback">{{ $message }}</span>
                                        @enderror
                                    </div>
                                @else
                                    <div class="sfp-field">
                                        <input type="text" name="{{ $item['key'] }}" class="sfp-input" value="{{ old($item['key'], $item['value']) }}" placeholder="{{ $item['placeholder'] ?? '' }}">
                                        @error($item['key'])
                                            <span class="sfp-invalid-feedback">{{ $message }}</span>
                                        @enderror
                                    </div>
                                @endif
                            </div>

                            <div class="modal-footer">
                                <button type="button" class="sfp-btn-outline" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="sfp-btn-primary">Save</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endcan
    @endforeach
@endsection
