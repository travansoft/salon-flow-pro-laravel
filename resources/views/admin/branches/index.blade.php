@extends('layouts.admin')

@section('title', 'Branches')

@section('content')
    <div class="sfp-page-header">
        <div>
            <h1 class="sfp-page-title">Branches</h1>
            <p class="sfp-page-subtitle">Each branch has its own staff assignments, catalogue, and invoice numbering.</p>
        </div>
        @can('branches.create')
            <a href="{{ $tenantUrl->route('branches.create') }}" class="sfp-btn-pill-dark">+ Add branch</a>
        @endcan
    </div>

    <div class="sfp-table-wrap">
        <div class="sfp-table-head-row" style="grid-template-columns:1fr 1fr 1fr 120px">
            <span>Name</span>
            <span>Invoice prefix</span>
            <span>Address</span>
            <span></span>
        </div>
        @forelse ($branches as $branch)
            <div class="sfp-table-row" style="grid-template-columns:1fr 1fr 1fr 120px">
                <div>
                    <div style="font-size:14.5px">{{ $branch->name }}</div>
                    @unless ($branch->is_active)
                        <span class="sfp-pill sfp-pill-neutral">Disabled</span>
                    @endunless
                </div>
                <span class="sfp-mono" style="font-size:13px">{{ $branch->invoice_prefix }}</span>
                <span style="font-size:13.5px;color:#66736F">{{ $branch->address ?? '—' }}</span>
                <div style="display:flex;align-items:center;justify-content:flex-end;gap:12px">
                    <a href="{{ $tenantUrl->route('branches.show', $branch) }}" style="font-size:12.5px;color:#66736F">View</a>
                    @can('branches.edit')
                        <a href="{{ $tenantUrl->route('branches.edit', $branch) }}" style="font-size:12.5px;color:#1B4B8F">Edit</a>
                    @endcan
                </div>
            </div>
        @empty
            <div class="sfp-table-row" style="grid-template-columns:1fr">
                <span style="color:#94A19D;font-size:13.5px">No branches yet.</span>
            </div>
        @endforelse
    </div>
@endsection
