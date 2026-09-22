@extends('layouts.super-admin')

@section('title', 'Tenant Users')

@section('content')
    <div class="sfp-page-header">
        <div>
            <h1 class="sfp-page-title">Users &mdash; {{ $tenant->name }}</h1>
            <p class="sfp-page-subtitle">Login accounts for this tenant.</p>
        </div>
        <div>
            <a href="{{ $superAdminUrl->route('superAdmin.tenants.users.create', $tenant) }}" class="sfp-btn-pill-dark">+ Add user</a>
        </div>
    </div>

    <div class="sfp-table-wrap">
        <div class="sfp-table-head-row" style="grid-template-columns: 2fr 1.5fr 1.5fr 1fr 1.5fr;">
            <div>Name</div>
            <div>Username</div>
            <div>Email</div>
            <div>Status</div>
            <div></div>
        </div>

        @foreach ($users as $user)
            <div class="sfp-table-row" style="grid-template-columns: 2fr 1.5fr 1.5fr 1fr 1.5fr;">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <div class="sfp-avatar-chip">{{ strtoupper(substr($user->name, 0, 1)) }}</div>
                    <span>{{ $user->name }}</span>
                </div>
                <div>{{ $user->username }}</div>
                <div>{{ $user->email ?? '—' }}</div>
                <div>
                    @if ($user->isLoginEnabled())
                        <span class="sfp-pill sfp-pill-green">Enabled</span>
                    @else
                        <span class="sfp-pill sfp-pill-amber">Disabled</span>
                    @endif
                </div>
                <div style="display: flex; gap: 12px; align-items: center;">
                    <a href="{{ $superAdminUrl->route('superAdmin.tenants.users.edit', [$tenant, $user]) }}" class="sfp-btn-outline">Edit</a>
                    <form action="{{ $superAdminUrl->route('superAdmin.tenants.users.toggleLogin', [$tenant, $user]) }}" method="POST">
                        @csrf
                        @method('PUT')
                        <button type="submit" class="sfp-btn-link-danger">{{ $user->isLoginEnabled() ? 'Disable' : 'Enable' }}</button>
                    </form>
                </div>
            </div>
        @endforeach
    </div>
@endsection
