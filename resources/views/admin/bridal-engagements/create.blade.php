@extends('layouts.admin')

@section('title', 'New Engagement')

@section('content')
    <div class="sfp-page-header">
        <div>
            <h1 class="sfp-page-title">New bridal engagement</h1>
            <p class="sfp-page-subtitle">Enter the contact number first; existing clients are suggested.</p>
        </div>
    </div>

    <div class="sfp-card">
        <form action="{{ $tenantUrl->route('bridalEngagements.store') }}" method="POST">
            @csrf

            @include('admin.bridal-engagements.partials.form')

            <div class="sfp-form-actions">
                <button type="submit" class="sfp-btn-primary">Create engagement</button>
                <a href="{{ $tenantUrl->route('bridalEngagements.index') }}" class="sfp-btn-outline">Cancel</a>
            </div>
        </form>
    </div>
@endsection
