@extends('layouts.super-admin')

@section('title', 'Edit Role')

@section('content')
    <div class="sfp-page-header">
        <div>
            <h1 class="sfp-page-title">Edit role</h1>
            <p class="sfp-page-subtitle">{{ $role->name }} &mdash; changes apply to every tenant using this role.</p>
        </div>
    </div>

    <div class="sfp-card">
        <form action="{{ $superAdminUrl->route('superAdmin.roles.update', $role) }}" method="POST">
            @csrf
            @method('PUT')

            @include('admin.roles._form-fields')

            <div class="sfp-form-actions">
                <button type="submit" class="sfp-btn-primary">Save</button>
                <a href="{{ $superAdminUrl->route('superAdmin.roles.index') }}" class="sfp-btn-outline">Cancel</a>
            </div>
        </form>
    </div>
@endsection
