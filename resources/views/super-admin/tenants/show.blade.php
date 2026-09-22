@extends('layouts.super-admin')

@section('title', $tenant->name)

@section('content')
    <div class="sfp-page-header">
        <div>
            <h1 class="sfp-page-title">{{ $tenant->name }}</h1>
            <p class="sfp-page-subtitle">{{ $tenant->subdomain }} &middot; {{ $tenant->slug }}</p>
        </div>
        <div style="display: flex; gap: 12px;">
            <a href="{{ $superAdminUrl->route('superAdmin.tenants.users.index', $tenant) }}" class="sfp-btn-outline">Manage users</a>
            <a href="{{ $superAdminUrl->route('superAdmin.tenants.edit', $tenant) }}" class="sfp-btn-pill-dark">Edit</a>
        </div>
    </div>

    <div class="sfp-card">
        <div class="sfp-card-title">Details</div>
        <p><strong>Status:</strong> {{ $tenant->is_active ? 'Active' : 'Inactive' }}</p>
        <p><strong>Custom domain:</strong> {{ $tenant->custom_domain ?? '—' }}</p>
        <p><strong>Legal name:</strong> {{ $tenant->legal_name ?? '—' }}</p>
        <p><strong>Phone:</strong> {{ $tenant->phone ?? '—' }}</p>
        <p><strong>GSTIN:</strong> {{ $tenant->gst_number ?? '—' }}</p>
    </div>
@endsection
