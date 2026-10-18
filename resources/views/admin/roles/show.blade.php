@extends('layouts.admin')

@section('title', $role->name)

@section('content')
    <div class="sfp-page-header">
        <div>
            <h1 class="sfp-page-title">{{ $role->name }}</h1>
            <p class="sfp-page-subtitle">{{ $role->description ?? 'No description.' }} &middot; {{ $userCount }} {{ \Illuminate\Support\Str::plural('user', $userCount) }}</p>
        </div>
        @if ($role->tenant_id)
            @can('roles.edit')
                <a href="{{ $tenantUrl->route('roles.edit', $role) }}" class="sfp-btn-pill-dark">Edit</a>
            @endcan
        @endif
    </div>

    <div class="sfp-card">
        @include('admin.roles._permission-matrix', ['readonly' => true])
    </div>
@endsection
