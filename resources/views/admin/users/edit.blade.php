@extends('layouts.admin')

@section('title', 'Edit User')

@section('content')
    <div class="sfp-page-header">
        <div>
            <h1 class="sfp-page-title">Edit user</h1>
            <p class="sfp-page-subtitle">{{ $managedUser->name }}</p>
        </div>
    </div>

    <div class="sfp-card">
        <form action="{{ $tenantUrl->route('users.update', $managedUser) }}" method="POST">
            @csrf
            @method('PUT')

            @include('admin.users._form-fields')

            <div class="sfp-form-actions">
                <button type="submit" class="sfp-btn-primary">Save</button>
                <a href="{{ $tenantUrl->route('users.index') }}" class="sfp-btn-outline">Cancel</a>
            </div>
        </form>
    </div>
@endsection
