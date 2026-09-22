@extends('layouts.super-admin')

@section('title', 'Tenants')

@section('content')
    <div class="sfp-page-header">
        <div>
            <h1 class="sfp-page-title">Tenants</h1>
            <p class="sfp-page-subtitle">Every salon studio on the platform.</p>
        </div>
        <div>
            <a href="{{ $superAdminUrl->route('superAdmin.tenants.create') }}" class="sfp-btn-pill-dark">+ Add tenant</a>
        </div>
    </div>

    <div class="sfp-table-wrap">
        <div class="sfp-table-head-row" style="grid-template-columns: 2fr 1.5fr 1.5fr 1fr 1.5fr;">
            <div>Name</div>
            <div>Subdomain</div>
            <div>Slug</div>
            <div>Status</div>
            <div></div>
        </div>

        @foreach ($tenants as $tenant)
            <div class="sfp-table-row" style="grid-template-columns: 2fr 1.5fr 1.5fr 1fr 1.5fr;">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <div class="sfp-avatar-chip">{{ strtoupper(substr($tenant->name, 0, 1)) }}</div>
                    <span>{{ $tenant->name }}</span>
                </div>
                <div>{{ $tenant->subdomain }}</div>
                <div>{{ $tenant->slug }}</div>
                <div>
                    @if ($tenant->is_active)
                        <span class="sfp-pill sfp-pill-green">Active</span>
                    @else
                        <span class="sfp-pill sfp-pill-neutral">Inactive</span>
                    @endif
                </div>
                <div style="display: flex; gap: 12px; align-items: center;">
                    <a href="{{ $superAdminUrl->route('superAdmin.tenants.show', $tenant) }}" class="sfp-btn-outline">View</a>
                    <a href="{{ $superAdminUrl->route('superAdmin.tenants.edit', $tenant) }}" class="sfp-btn-outline">Edit</a>
                    <form action="{{ $superAdminUrl->route('superAdmin.tenants.destroy', $tenant) }}" method="POST">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="sfp-btn-link-danger">{{ $tenant->is_active ? 'Deactivate' : 'Activate' }}</button>
                    </form>
                </div>
            </div>
        @endforeach
    </div>
@endsection
