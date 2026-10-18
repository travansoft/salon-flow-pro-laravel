@extends('layouts.super-admin')

@section('title', 'Roles')

@section('content')
    <div class="sfp-page-header">
        <div>
            <h1 class="sfp-page-title">Roles</h1>
            <p class="sfp-page-subtitle">Standard roles available to every tenant. Changes apply to all tenants immediately; tenants can also build their own custom roles.</p>
        </div>
        <div>
            <a href="{{ $superAdminUrl->route('superAdmin.roles.create') }}" class="sfp-btn-pill-dark">+ Add role</a>
        </div>
    </div>

    @error('role')
        <div class="sfp-alert-error">{{ $message }}</div>
    @enderror

    <div class="sfp-table-wrap">
        <div class="sfp-table-head-row" style="grid-template-columns: 1.2fr 2.5fr 1fr 1.5fr;">
            <div>Role</div>
            <div>Description</div>
            <div>Permissions</div>
            <div></div>
        </div>

        @foreach ($roles as $role)
            <div class="sfp-table-row" style="grid-template-columns: 1.2fr 2.5fr 1fr 1.5fr;">
                <div>{{ $role->name }}</div>
                <div style="font-size: 13.5px; color: #66736F;">{{ $role->description ?? '—' }}</div>
                <div>{{ $role->permissions->count() }}</div>
                <div style="display: flex; gap: 12px; align-items: center;">
                    <a href="{{ $superAdminUrl->route('superAdmin.roles.edit', $role) }}" class="sfp-btn-outline">Edit</a>
                    @unless ($role->isBuiltIn())
                        <form action="{{ $superAdminUrl->route('superAdmin.roles.destroy', $role) }}" method="POST" onsubmit="return confirm('Delete this role?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="sfp-btn-link-danger">Delete</button>
                        </form>
                    @endunless
                </div>
            </div>
        @endforeach
    </div>
@endsection
