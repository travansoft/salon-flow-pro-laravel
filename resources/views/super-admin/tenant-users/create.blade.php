@extends('layouts.super-admin')

@section('title', 'Add User')

@section('content')
    <div class="sfp-page-header">
        <div>
            <h1 class="sfp-page-title">Add user &mdash; {{ $tenant->name }}</h1>
        </div>
    </div>

    <div class="sfp-card">
        <form action="{{ $superAdminUrl->route('superAdmin.tenants.users.store', $tenant) }}" method="POST">
            @csrf

            <div class="sfp-field">
                <label class="sfp-label">Name *</label>
                <input type="text" name="name" class="sfp-input" value="{{ old('name') }}">
                @error('name')
                    <span class="sfp-invalid-feedback">{{ $message }}</span>
                @enderror
            </div>

            <div class="sfp-field">
                <label class="sfp-label">Username *</label>
                <input type="text" name="username" class="sfp-input" value="{{ old('username') }}">
                @error('username')
                    <span class="sfp-invalid-feedback">{{ $message }}</span>
                @enderror
            </div>

            <div class="sfp-field">
                <label class="sfp-label">Email</label>
                <input type="email" name="email" class="sfp-input" value="{{ old('email') }}">
                @error('email')
                    <span class="sfp-invalid-feedback">{{ $message }}</span>
                @enderror
            </div>

            <div class="sfp-field">
                <label class="sfp-label">Password *</label>
                <input type="password" name="password" class="sfp-input">
                @error('password')
                    <span class="sfp-invalid-feedback">{{ $message }}</span>
                @enderror
            </div>

            <div class="sfp-field">
                <label class="sfp-label">Roles</label>
                @foreach ($roles as $role)
                    <div class="form-check">
                        <input type="checkbox" name="roles[]" value="{{ $role->name }}" id="role-{{ $role->id }}" class="form-check-input" @checked(collect(old('roles', []))->contains($role->name))>
                        <label class="form-check-label" for="role-{{ $role->id }}">{{ $role->name }}</label>
                    </div>
                @endforeach
                @error('roles')
                    <span class="sfp-invalid-feedback">{{ $message }}</span>
                @enderror
            </div>

            <div class="sfp-form-actions">
                <button type="submit" class="sfp-btn-primary">Save</button>
            </div>
        </form>
    </div>
@endsection
