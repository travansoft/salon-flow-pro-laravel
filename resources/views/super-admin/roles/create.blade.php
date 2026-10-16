@extends('layouts.super-admin')

@section('title', 'Add Role')

@section('content')
    <div class="sfp-page-header">
        <div>
            <h1 class="sfp-page-title">Add role</h1>
            <p class="sfp-page-subtitle">The new role becomes available to every tenant.</p>
        </div>
    </div>

    <div class="sfp-card">
        <form action="{{ $superAdminUrl->route('superAdmin.roles.store') }}" method="POST">
            @csrf

            @include('admin.roles._form-fields')

            <div class="sfp-form-actions">
                <button type="submit" class="sfp-btn-primary">Save</button>
                <a href="{{ $superAdminUrl->route('superAdmin.roles.index') }}" class="sfp-btn-outline">Cancel</a>
            </div>
        </form>
    </div>
@endsection
