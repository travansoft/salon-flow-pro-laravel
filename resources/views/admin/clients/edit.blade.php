@extends('layouts.admin')

@section('title', 'Edit Client')

@section('content')
    <div class="sfp-page-header">
        <div>
            <h1 class="sfp-page-title">Edit {{ $client->name }}</h1>
            <p class="sfp-page-subtitle">Update client details, family links, and notes.</p>
        </div>
    </div>

    <div class="sfp-card">
        <form action="{{ $tenantUrl->route('clients.update', $client) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="sfp-field">
                <label class="sfp-label">Name</label>
                <input type="text" name="name" class="sfp-input" value="{{ old('name', $client->name) }}">
                @error('name')
                    <span class="sfp-invalid-feedback">{{ $message }}</span>
                @enderror
            </div>

            <div class="sfp-split-2">
                <div class="sfp-field">
                    <label class="sfp-label">Phone</label>
                    <input type="text" name="phone" class="sfp-input" value="{{ old('phone', $client->phone) }}">
                    @error('phone')
                        <span class="sfp-invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>

                <div class="sfp-field">
                    <label class="sfp-label">Email</label>
                    <input type="email" name="email" class="sfp-input" value="{{ old('email', $client->email) }}">
                    @error('email')
                        <span class="sfp-invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>
            </div>

            <div class="sfp-field">
                <label class="sfp-label">Family link</label>
                <input type="text" name="family_link" class="sfp-input" value="{{ old('family_link', $client->family_link) }}">
                @error('family_link')
                    <span class="sfp-invalid-feedback">{{ $message }}</span>
                @enderror
            </div>

            <div class="sfp-field">
                <label class="sfp-label">Notes</label>
                <textarea name="notes" class="sfp-textarea">{{ old('notes', $client->notes) }}</textarea>
                @error('notes')
                    <span class="sfp-invalid-feedback">{{ $message }}</span>
                @enderror
            </div>

            <div class="sfp-field">
                <label class="sfp-label">GSTIN (optional)</label>
                <input type="text" name="gst_number" class="sfp-input" value="{{ old('gst_number', $client->gst_number) }}" placeholder="For B2B invoices">
                @error('gst_number')
                    <span class="sfp-invalid-feedback">{{ $message }}</span>
                @enderror
            </div>

            <div class="sfp-form-actions">
                <button type="submit" class="sfp-btn-primary">Save</button>
                <a href="{{ $tenantUrl->route('clients.show', $client) }}" class="sfp-btn-outline">Cancel</a>
            </div>
        </form>
    </div>
@endsection
