@extends('layouts.super-admin')

@section('title', 'Admin Users')

@section('content')
    <div class="sfp-page-header">
        <div>
            <h1 class="sfp-page-title">Admin users</h1>
            <p class="sfp-page-subtitle">Accounts with access to this platform panel.</p>
        </div>
        <div>
            <a href="{{ $superAdminUrl->route('superAdmin.platformAdmins.create') }}" class="sfp-btn-pill-dark">+ Add admin</a>
        </div>
    </div>

    <div class="sfp-table-wrap">
        <div class="sfp-table-head-row" style="grid-template-columns: 2fr 1.5fr 1.5fr 1.5fr;">
            <div>Name</div>
            <div>Username</div>
            <div>Email</div>
            <div></div>
        </div>

        @foreach ($platformAdmins as $platformAdmin)
            <div class="sfp-table-row" style="grid-template-columns: 2fr 1.5fr 1.5fr 1.5fr;">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <div class="sfp-avatar-chip">{{ strtoupper(substr($platformAdmin->name, 0, 1)) }}</div>
                    <span>{{ $platformAdmin->name }}</span>
                </div>
                <div>{{ $platformAdmin->username }}</div>
                <div>{{ $platformAdmin->email ?? '—' }}</div>
                <div style="display: flex; gap: 12px; align-items: center;">
                    <a href="{{ $superAdminUrl->route('superAdmin.platformAdmins.edit', $platformAdmin) }}" class="sfp-btn-outline">Edit</a>
                    @if (auth('super_admin')->id() !== $platformAdmin->id)
                        <form action="{{ $superAdminUrl->route('superAdmin.platformAdmins.destroy', $platformAdmin) }}" method="POST">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="sfp-btn-link-danger">Remove</button>
                        </form>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
@endsection
