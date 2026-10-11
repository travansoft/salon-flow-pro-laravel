@extends('layouts.admin')

@section('title', 'My Profile')

@section('content')
    <div class="sfp-page-header">
        <div>
            <h1 class="sfp-page-title">My profile</h1>
            <p class="sfp-page-subtitle">Your account details.</p>
        </div>
    </div>

    <div class="sfp-card">
        <div class="sfp-field">
            <label class="sfp-label">Name</label>
            <div>{{ $user->name }}</div>
        </div>

        <div class="sfp-field">
            <label class="sfp-label">Role</label>
            <div>{{ $role ?? '—' }}</div>
        </div>

        <div class="sfp-field">
            <label class="sfp-label">Username</label>
            <div>{{ $user->username }}</div>
        </div>

        <div class="sfp-form-actions">
            <a href="{{ $tenantUrl->route('password.edit') }}" class="sfp-btn-primary">Change password</a>
        </div>
    </div>
@endsection
