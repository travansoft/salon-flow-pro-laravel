@extends('layouts.admin')

@section('title', $branch->name)

@section('content')
    <div class="sfp-page-header">
        <div>
            <h1 class="sfp-page-title">{{ $branch->name }}</h1>
            <p class="sfp-page-subtitle">Invoice prefix <span class="sfp-mono">{{ $branch->invoice_prefix }}</span></p>
        </div>
        @can('branches.edit')
            <a href="{{ $tenantUrl->route('branches.edit', $branch) }}" class="sfp-btn-outline">Edit branch</a>
        @endcan
    </div>

    <div class="sfp-card" style="margin-bottom:16px">
        <div style="display:flex;align-items:center;gap:28px;flex-wrap:wrap">
            <div>
                <div class="sfp-label" style="margin-bottom:6px">Status</div>
                @if ($branch->is_active)
                    <span class="sfp-pill sfp-pill-green">Active</span>
                @else
                    <span class="sfp-pill sfp-pill-neutral">Disabled</span>
                @endif
            </div>
            <div>
                <div class="sfp-label" style="margin-bottom:6px">Address</div>
                <div style="font-size:14px">{{ $branch->address ?? '—' }}</div>
            </div>
            <div>
                <div class="sfp-label" style="margin-bottom:6px">Phone</div>
                <div style="font-size:14px">{{ $branch->phone ?? '—' }}</div>
            </div>
            <div>
                <div class="sfp-label" style="margin-bottom:6px">GST state code</div>
                <div style="font-size:14px">{{ $branch->gst_state_code ?? '—' }}</div>
            </div>
        </div>
    </div>
@endsection
