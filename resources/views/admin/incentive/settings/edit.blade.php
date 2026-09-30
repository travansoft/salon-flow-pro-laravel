@extends('layouts.admin')

@section('title', 'Incentive Settings')

@section('content')
    <div class="sfp-page-header">
        <div>
            <h1 class="sfp-page-title">Incentive settings</h1>
            <p class="sfp-page-subtitle">The referral split and the achievement slabs apply to the whole business.</p>
        </div>
    </div>

    @include('admin.incentive._nav')

    <div class="sfp-card" style="margin-bottom:14px">
        <h2 class="sfp-page-subtitle" style="font-weight:600;margin-bottom:10px">Referral split</h2>
        <form action="{{ $tenantUrl->route('incentiveSettings.update') }}" method="POST">
            @csrf
            @method('PUT')

            <div class="sfp-split-2">
                <div class="sfp-field">
                    <label class="sfp-label">Servicing staff (%)</label>
                    <input type="number" step="0.01" min="0" max="100" name="servicing_share_percent" class="sfp-input" value="{{ old('servicing_share_percent', $settings->servicing_share_percent) }}">
                    @error('servicing_share_percent')
                        <span class="sfp-invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>

                <div class="sfp-field">
                    <label class="sfp-label">Referring staff (%)</label>
                    <input type="number" step="0.01" min="0" max="100" name="referring_share_percent" class="sfp-input" value="{{ old('referring_share_percent', $settings->referring_share_percent) }}">
                    @error('referring_share_percent')
                        <span class="sfp-invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>
            </div>

            <div class="sfp-form-actions">
                <button type="submit" class="sfp-btn-primary">Save split</button>
            </div>
        </form>
    </div>

    <div class="sfp-table-wrap">
        <div class="sfp-table-head-row" style="grid-template-columns:1fr 1fr 200px">
            <span>Achievement at least (%)</span>
            <span>Incentive (% of achieved)</span>
            <span></span>
        </div>

        @forelse ($slabs as $slab)
            <div class="sfp-table-row" style="grid-template-columns:1fr 1fr 200px">
                <form id="slab-{{ $slab->id }}" action="{{ $tenantUrl->route('incentiveSlabs.update', $slab) }}" method="POST" style="display:contents">
                    @csrf
                    @method('PUT')
                    <input type="number" step="0.01" min="0" max="1000" name="min_achievement_percent" class="sfp-input" value="{{ $slab->min_achievement_percent }}">
                    <input type="number" step="0.01" min="0" max="100" name="incentive_percent" class="sfp-input" value="{{ $slab->incentive_percent }}">
                </form>
                <div style="display:flex;align-items:center;justify-content:flex-end;gap:12px">
                    <button type="submit" form="slab-{{ $slab->id }}" class="sfp-btn-outline">Save</button>
                    @can('incentives.delete')
                        <form action="{{ $tenantUrl->route('incentiveSlabs.destroy', $slab) }}" method="POST" style="display:inline">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="sfp-btn-link-danger">Remove</button>
                        </form>
                    @endcan
                </div>
            </div>
        @empty
            <div class="sfp-table-row">
                <span style="color:#66736F">No slabs configured. No incentive is paid.</span>
            </div>
        @endforelse

        @can('incentives.create')
            <div class="sfp-table-row" style="grid-template-columns:1fr 1fr 200px">
                <form id="new-slab" action="{{ $tenantUrl->route('incentiveSlabs.store') }}" method="POST" style="display:contents">
                    @csrf
                    <input type="number" step="0.01" min="0" max="1000" name="min_achievement_percent" class="sfp-input" placeholder="e.g. 110" value="{{ old('min_achievement_percent') }}">
                    <input type="number" step="0.01" min="0" max="100" name="incentive_percent" class="sfp-input" placeholder="e.g. 6" value="{{ old('incentive_percent') }}">
                </form>
                <div style="display:flex;align-items:center;justify-content:flex-end">
                    <button type="submit" form="new-slab" class="sfp-btn-pill-dark">+ Add slab</button>
                </div>
            </div>
        @endcan
    </div>

    @if ($errors->has('min_achievement_percent') || $errors->has('incentive_percent'))
        <div class="sfp-invalid-feedback" style="margin-top:8px">
            {{ $errors->first('min_achievement_percent') ?: $errors->first('incentive_percent') }}
        </div>
    @endif
@endsection
