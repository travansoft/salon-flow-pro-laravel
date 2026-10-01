@extends('layouts.admin')

@section('title', 'Staff Targets')

@section('content')
    <div class="sfp-page-header">
        <div>
            <h1 class="sfp-page-title">Monthly targets</h1>
            <p class="sfp-page-subtitle">Target for {{ $month->format('F Y') }}, as GST-inclusive service value. Leave blank for no target.</p>
        </div>
        <form method="GET" class="sfp-row">
            <input type="month" name="month" class="sfp-input" value="{{ $month->format('Y-m') }}">
            <button type="submit" class="sfp-btn-primary">Show</button>
        </form>
    </div>

    @include('admin.incentive._nav')

    <form action="{{ $tenantUrl->route('staffTargets.store') }}" method="POST">
        @csrf
        <input type="hidden" name="month" value="{{ $month->format('Y-m') }}">

        <div class="sfp-table-wrap">
            <div class="sfp-table-head-row" style="grid-template-columns:1fr 200px">
                <span>Staff</span>
                <span>Target (&#8377;)</span>
            </div>

            @forelse ($staff as $member)
                <div class="sfp-table-row" style="grid-template-columns:1fr 200px">
                    <span>{{ $member->name }}</span>
                    <input type="number" step="0.01" min="0" name="targets[{{ $member->id }}]" class="sfp-input" value="{{ old("targets.{$member->id}", $targets->get($member->id)) }}">
                </div>
            @empty
                <div class="sfp-table-row">
                    <span style="color:#66736F">No active staff.</span>
                </div>
            @endforelse
        </div>

        @error('targets')
            <span class="sfp-invalid-feedback">{{ $message }}</span>
        @enderror
        @error('targets.*')
            <span class="sfp-invalid-feedback">{{ $message }}</span>
        @enderror

        <div class="sfp-form-actions">
            <button type="submit" class="sfp-btn-primary">Save targets</button>
        </div>
    </form>

    <form action="{{ $tenantUrl->route('staffTargets.copy') }}" method="POST" style="margin-top:10px">
        @csrf
        <input type="hidden" name="from_month" value="{{ $month->copy()->subMonth()->format('Y-m') }}">
        <input type="hidden" name="to_month" value="{{ $month->format('Y-m') }}">
        <button type="submit" class="sfp-btn-outline">Copy missing targets from {{ $month->copy()->subMonth()->format('F Y') }}</button>
    </form>
@endsection
