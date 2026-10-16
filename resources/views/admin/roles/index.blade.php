@extends('layouts.admin')

@section('title', 'Roles')

@section('content')
    <div class="sfp-page-header">
        <div>
            <h1 class="sfp-page-title">Roles &amp; access</h1>
            <p class="sfp-page-subtitle">Decide what each type of user can see and do. Create your own roles in addition to the standard ones.</p>
        </div>
        @can('roles.create')
            <a href="{{ $tenantUrl->route('roles.create') }}" class="sfp-btn-pill-dark">+ Add role</a>
        @endcan
    </div>

    @error('role')
        <div class="sfp-alert-error">{{ $message }}</div>
    @enderror

    @foreach ([['Your roles', $customRoles, 'You have not created any custom roles yet.'], ['Standard roles', $systemRoles, 'No standard roles.']] as [$heading, $roles, $emptyText])
        <div class="sfp-card-title" style="margin:24px 0 10px">{{ $heading }}</div>
        <div class="sfp-table-wrap">
            <div class="sfp-table-head-row" style="grid-template-columns:1.2fr 2fr 1fr 140px">
                <span>Role</span>
                <span>Description</span>
                <span>Permissions</span>
                <span></span>
            </div>
            @forelse ($roles as $role)
                <div class="sfp-table-row" style="grid-template-columns:1.2fr 2fr 1fr 140px">
                    <span style="font-size:14.5px">{{ $role->name }}</span>
                    <span style="font-size:13.5px;color:#66736F">{{ $role->description ?? '—' }}</span>
                    <span style="font-size:13.5px">{{ $role->permissions->count() }}</span>
                    <div style="display:flex;align-items:center;justify-content:flex-end;gap:12px">
                        <a href="{{ $tenantUrl->route('roles.show', $role) }}" style="font-size:12.5px;color:#66736F">View</a>
                        @if ($role->tenant_id)
                            @can('roles.edit')
                                <a href="{{ $tenantUrl->route('roles.edit', $role) }}" style="font-size:12.5px;color:#1B4B8F">Edit</a>
                            @endcan
                        @endif
                    </div>
                </div>
            @empty
                <div class="sfp-table-row" style="grid-template-columns:1fr">
                    <span style="color:#94A19D;font-size:13.5px">{{ $emptyText }}</span>
                </div>
            @endforelse
        </div>
    @endforeach
@endsection
