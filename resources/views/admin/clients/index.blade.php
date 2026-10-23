@extends('layouts.admin')

@section('title', 'Clients')

@section('content')
    <div class="sfp-page-header">
        <div>
            <h1 class="sfp-page-title">Clients</h1>
            <p class="sfp-page-subtitle">Search by name or mobile number to find an existing client.</p>
        </div>
        <div style="display:flex;gap:9px;align-items:center;flex-wrap:wrap">
            <form method="GET" style="margin:0">
                <input type="text" name="search" value="{{ $search }}" placeholder="Search name or mobile…" class="sfp-input" style="margin-bottom:0;width:280px;border-radius:999px">
            </form>
            @can('clients.view')
                <a href="{{ $tenantUrl->route('clients.export') }}?{{ http_build_query(array_filter(['search' => $search])) }}" class="sfp-btn-outline"><i class="bi bi-file-earmark-excel"></i> Export to Excel</a>
            @endcan
            @can('clients.create')
                <a href="{{ $tenantUrl->route('clients.create') }}" class="sfp-btn-pill-dark">+ Add client</a>
            @endcan
        </div>
    </div>

    <div class="sfp-table-wrap">
        <div class="sfp-table-head-row" style="grid-template-columns:1.6fr 1fr 1fr 120px">
            <span>Client</span>
            <span>Phone</span>
            <span>Email</span>
            <span></span>
        </div>

        @forelse ($clients as $client)
            <div class="sfp-table-row" style="grid-template-columns:1.6fr 1fr 1fr 120px">
                <div style="display:flex;align-items:center;gap:14px">
                    <div class="sfp-avatar-chip" style="width:40px;height:40px;font-size:16px">{{ strtoupper(substr($client->name, 0, 1)) }}</div>
                    <div>
                        <div style="font-size:15px;font-weight:500">{{ $client->name }}</div>
                        @if ($client->is_frequent_no_show)
                            <span class="sfp-pill sfp-pill-red" style="margin-top:5px">Frequent no-show</span>
                        @endif
                    </div>
                </div>
                <span style="font-size:14px;color:#66736F">{{ $client->phone }}</span>
                <span style="font-size:14px;color:#66736F">{{ $client->email ?? '—' }}</span>
                <div style="text-align:right">
                    <a href="{{ $tenantUrl->route('clients.show', $client) }}" class="sfp-btn-outline">View</a>
                </div>
            </div>
        @empty
            <div class="sfp-table-row" style="grid-template-columns:1fr">
                <p style="color:#66736F;margin:0">No clients found.</p>
            </div>
        @endforelse
    </div>
@endsection
