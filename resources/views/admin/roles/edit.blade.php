@extends('layouts.admin')

@section('title', 'Edit Role')

@section('content')
    <div class="sfp-page-header">
        <div>
            <h1 class="sfp-page-title">Edit role</h1>
            <p class="sfp-page-subtitle">{{ $role->name }}</p>
        </div>
    </div>

    <div class="sfp-card">
        <form action="{{ $tenantUrl->route('roles.update', $role) }}" method="POST">
            @csrf
            @method('PUT')

            @include('admin.roles._form-fields')

            <div class="sfp-form-actions">
                <button type="submit" class="sfp-btn-primary">Save</button>
                <a href="{{ $tenantUrl->route('roles.show', $role) }}" class="sfp-btn-outline">Cancel</a>
            </div>
        </form>

        @can('roles.delete')
            <form action="{{ $tenantUrl->route('roles.destroy', $role) }}" method="POST" style="margin-top:16px" onsubmit="return confirm('Delete this role?')">
                @csrf
                @method('DELETE')
                @error('role')
                    <span class="sfp-invalid-feedback">{{ $message }}</span>
                @enderror
                <button type="submit" class="sfp-btn-link-danger">Delete role</button>
            </form>
        @endcan
    </div>
@endsection
