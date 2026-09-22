@extends('layouts.super-admin')

@section('title', 'Platform Dashboard')

@section('content')
    <div class="sfp-page-header">
        <div>
            <h1 class="sfp-page-title">Platform overview</h1>
            <p class="sfp-page-subtitle">Tenants and administrators across SalonFlow Pro.</p>
        </div>
    </div>

    <div class="sfp-split-2">
        <div class="sfp-card">
            <div class="sfp-card-title">Tenants</div>
            <p style="font-size: 32px; font-weight: 600; margin: 8px 0;">{{ $tenants->count() }}</p>
            <a href="{{ $superAdminUrl->route('superAdmin.tenants.index') }}" class="sfp-btn-outline">Manage tenants</a>
        </div>

        <div class="sfp-card">
            <div class="sfp-card-title">Admin users</div>
            <p style="font-size: 32px; font-weight: 600; margin: 8px 0;">{{ $platformAdmins->count() }}</p>
            <a href="{{ $superAdminUrl->route('superAdmin.platformAdmins.index') }}" class="sfp-btn-outline">Manage admins</a>
        </div>
    </div>
@endsection
