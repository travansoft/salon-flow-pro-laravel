@extends('layouts.super-admin')

@section('title', 'Activity Log')

@section('content')
    <div class="sfp-page-header">
        <div>
            <h1 class="sfp-page-title">Activity log</h1>
            <p class="sfp-page-subtitle">Actions taken across tenants and admin accounts.</p>
        </div>
    </div>

    <div class="sfp-table-wrap">
        <div class="sfp-table-head-row" style="grid-template-columns: 1.5fr 3fr 1.5fr;">
            <div>Admin</div>
            <div>Action</div>
            <div>When</div>
        </div>

        @forelse ($logs as $log)
            <div class="sfp-table-row" style="grid-template-columns: 1.5fr 3fr 1.5fr;">
                <div>{{ $log->platformAdmin?->name ?? $log->platform_admin_name }}</div>
                <div>{{ $log->description }}</div>
                <div>{{ $log->created_at->format('d M Y, H:i') }}</div>
            </div>
        @empty
            <div class="sfp-table-row" style="grid-template-columns: 1fr;">
                <div style="color: #94A19D;">No activity recorded yet.</div>
            </div>
        @endforelse
    </div>

    <div style="margin-top: 16px;">
        {{ $logs->links() }}
    </div>
@endsection
