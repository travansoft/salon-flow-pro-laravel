@extends('layouts.admin')

@section('title', 'Edit Engagement')

@section('content')
    <div class="sfp-page-header">
        <div>
            <h1 class="sfp-page-title">Edit bridal engagement</h1>
            <p class="sfp-page-subtitle">{{ $engagement->client->name }}</p>
        </div>
    </div>

    <div class="sfp-card">
        <form action="{{ $tenantUrl->route('bridalEngagements.update', $engagement) }}" method="POST">
            @csrf
            @method('PUT')

            @include('admin.bridal-engagements.partials.form')

            <div class="sfp-form-actions">
                <button type="submit" class="sfp-btn-primary">Save changes</button>
                <a href="{{ $tenantUrl->route('bridalEngagements.show', $engagement) }}" class="sfp-btn-outline">Cancel</a>
            </div>
        </form>
    </div>
@endsection
