@extends('layouts.admin')

@section('title', 'Add User')

@section('content')
    <div class="sfp-page-header">
        <div>
            <h1 class="sfp-page-title">Add user</h1>
            <p class="sfp-page-subtitle">Give someone a login and choose what they can access.</p>
        </div>
    </div>

    @error('limit')
        <div class="sfp-alert-error">{{ $message }}</div>
    @enderror

    <div class="sfp-card">
        <form action="{{ $tenantUrl->route('users.store') }}" method="POST">
            @csrf

            @include('admin.users._form-fields')

            <div class="sfp-form-actions">
                <button type="submit" class="sfp-btn-primary">Save</button>
                <a href="{{ $tenantUrl->route('users.index') }}" class="sfp-btn-outline">Cancel</a>
            </div>
        </form>
    </div>
@endsection
