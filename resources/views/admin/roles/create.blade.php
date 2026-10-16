@extends('layouts.admin')

@section('title', 'Add Role')

@section('content')
    <div class="sfp-page-header">
        <div>
            <h1 class="sfp-page-title">Add role</h1>
            <p class="sfp-page-subtitle">You can only grant permissions that you hold yourself.</p>
        </div>
    </div>

    <div class="sfp-card">
        <form action="{{ $tenantUrl->route('roles.store') }}" method="POST">
            @csrf

            @include('admin.roles._form-fields')

            <div class="sfp-form-actions">
                <button type="submit" class="sfp-btn-primary">Save</button>
                <a href="{{ $tenantUrl->route('roles.index') }}" class="sfp-btn-outline">Cancel</a>
            </div>
        </form>
    </div>
@endsection
