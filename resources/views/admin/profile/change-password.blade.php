@extends('layouts.admin')

@section('title', 'Change Password')

@section('content')
    <div class="sfp-page-header">
        <div>
            <h1 class="sfp-page-title">Change password</h1>
            <p class="sfp-page-subtitle">Enter your current password and choose a new one.</p>
        </div>
    </div>

    <div class="sfp-card">
        <form action="{{ $tenantUrl->route('password.update') }}" method="POST">
            @csrf
            @method('PUT')

            <div class="sfp-field">
                <label class="sfp-label">Current password *</label>
                <input type="password" name="current_password" class="sfp-input" autocomplete="current-password">
                @error('current_password')
                    <span class="sfp-invalid-feedback">{{ $message }}</span>
                @enderror
            </div>

            <div class="sfp-field">
                <label class="sfp-label">New password *</label>
                <input type="password" name="password" class="sfp-input" autocomplete="new-password">
                @error('password')
                    <span class="sfp-invalid-feedback">{{ $message }}</span>
                @enderror
            </div>

            <div class="sfp-field">
                <label class="sfp-label">Confirm new password *</label>
                <input type="password" name="password_confirmation" class="sfp-input" autocomplete="new-password">
            </div>

            <div class="sfp-form-actions">
                <button type="submit" class="sfp-btn-primary">Change password</button>
                <a href="{{ $tenantUrl->route('profile.show') }}" class="sfp-btn-outline">Cancel</a>
            </div>
        </form>
    </div>
@endsection
