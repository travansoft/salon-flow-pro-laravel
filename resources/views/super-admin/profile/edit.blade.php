@extends('layouts.super-admin')

@section('title', 'My Profile')

@section('content')
    <div class="sfp-page-header">
        <div>
            <h1 class="sfp-page-title">My profile</h1>
            <p class="sfp-page-subtitle">Update your account details and password.</p>
        </div>
    </div>

    <div class="sfp-card">
        <form action="{{ $superAdminUrl->route('superAdmin.profile.update') }}" method="POST">
            @csrf
            @method('PUT')

            <div class="sfp-field">
                <label class="sfp-label">Name *</label>
                <input type="text" name="name" class="sfp-input" value="{{ old('name', $platformAdmin->name) }}">
                @error('name')
                    <span class="sfp-invalid-feedback">{{ $message }}</span>
                @enderror
            </div>

            <div class="sfp-field">
                <label class="sfp-label">Username *</label>
                <input type="text" name="username" class="sfp-input" value="{{ old('username', $platformAdmin->username) }}">
                @error('username')
                    <span class="sfp-invalid-feedback">{{ $message }}</span>
                @enderror
            </div>

            <div class="sfp-field">
                <label class="sfp-label">Email</label>
                <input type="email" name="email" class="sfp-input" value="{{ old('email', $platformAdmin->email) }}">
                @error('email')
                    <span class="sfp-invalid-feedback">{{ $message }}</span>
                @enderror
            </div>

            <div class="sfp-card-title" style="margin-top: 24px;">Change password</div>
            <p style="color: #66736F; font-size: 13px; margin-top: -8px;">Leave blank to keep your current password.</p>

            <div class="sfp-field">
                <label class="sfp-label">Current password</label>
                <input type="password" name="current_password" class="sfp-input">
                @error('current_password')
                    <span class="sfp-invalid-feedback">{{ $message }}</span>
                @enderror
            </div>

            <div class="sfp-field">
                <label class="sfp-label">New password</label>
                <input type="password" name="password" class="sfp-input">
                @error('password')
                    <span class="sfp-invalid-feedback">{{ $message }}</span>
                @enderror
            </div>

            <div class="sfp-field">
                <label class="sfp-label">Confirm new password</label>
                <input type="password" name="password_confirmation" class="sfp-input">
            </div>

            <div class="sfp-form-actions">
                <button type="submit" class="sfp-btn-primary">Save</button>
            </div>
        </form>
    </div>
@endsection
