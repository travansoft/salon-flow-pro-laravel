@extends('layouts.admin')

@section('title', 'Users')

@section('content')
    <div class="sfp-page-header">
        <div>
            <h1 class="sfp-page-title">Users &amp; access</h1>
            <p class="sfp-page-subtitle">{{ $activeUserCount }} of {{ $userLimit }} active users in use.</p>
        </div>
        <div style="display:flex;gap:12px">
            @can('roles.view')
                <a href="{{ $tenantUrl->route('roles.index') }}" class="sfp-btn-outline">Manage roles</a>
            @endcan
            @can('users.create')
                <a href="{{ $tenantUrl->route('users.create') }}" class="sfp-btn-pill-dark">+ Add user</a>
            @endcan
        </div>
    </div>

    @error('limit')
        <div class="sfp-alert-error">{{ $message }}</div>
    @enderror
    @error('user')
        <div class="sfp-alert-error">{{ $message }}</div>
    @enderror

    <div class="sfp-table-wrap">
        <div class="sfp-table-head-row" style="grid-template-columns:1.5fr 1fr 1.5fr 1fr 160px">
            <span>Name</span>
            <span>Username</span>
            <span>Roles</span>
            <span>Status</span>
            <span></span>
        </div>
        @forelse ($users as $user)
            <div class="sfp-table-row" style="grid-template-columns:1.5fr 1fr 1.5fr 1fr 160px">
                <span style="font-size:14.5px">{{ $user->name }}</span>
                <span class="sfp-mono" style="font-size:13px">{{ $user->username }}</span>
                <span style="font-size:13.5px">{{ $user->getRoleNames()->implode(', ') ?: '—' }}</span>
                <span>
                    @if ($user->isLoginEnabled())
                        <span class="sfp-pill sfp-pill-blue">Active</span>
                    @else
                        <span class="sfp-pill sfp-pill-amber">Disabled</span>
                    @endif
                </span>
                <div style="display:flex;align-items:center;justify-content:flex-end;gap:12px">
                    @can('users.edit')
                        <a href="{{ $tenantUrl->route('users.edit', $user) }}" style="font-size:12.5px;color:#1B4B8F">Edit</a>
                        @unless ($user->is(auth()->user()))
                            <form action="{{ $tenantUrl->route('users.toggleLogin', $user) }}" method="POST">
                                @csrf
                                @method('PUT')
                                <button type="submit" class="{{ $user->isLoginEnabled() ? 'sfp-btn-link-danger' : 'sfp-btn-link' }}" style="font-size:12px">{{ $user->isLoginEnabled() ? 'Disable' : 'Enable' }}</button>
                            </form>
                        @endunless
                    @endcan
                </div>
            </div>
        @empty
            <div class="sfp-table-row" style="grid-template-columns:1fr">
                <span style="color:#94A19D;font-size:13.5px">No users yet.</span>
            </div>
        @endforelse
    </div>
@endsection
