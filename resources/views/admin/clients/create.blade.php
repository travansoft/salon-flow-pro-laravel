@extends('layouts.admin')

@section('title', 'Add Client')

@section('content')
    <div class="sfp-page-header">
        <div>
            <h1 class="sfp-page-title">Add client</h1>
            <p class="sfp-page-subtitle">New clients can be booked and billed once saved.</p>
        </div>
    </div>

    <div class="sfp-card">
        <form action="{{ $tenantUrl->route('clients.store') }}" method="POST">
            @csrf

            <div class="sfp-field">
                <label class="sfp-label">Name</label>
                <input type="text" name="name" class="sfp-input" value="{{ old('name') }}">
                @error('name')
                    <span class="sfp-invalid-feedback">{{ $message }}</span>
                @enderror
            </div>

            <div class="sfp-split-2">
                <div class="sfp-field">
                    <label class="sfp-label">Phone</label>
                    <input type="text" name="phone" class="sfp-input" value="{{ old('phone') }}">
                    @error('phone')
                        <span class="sfp-invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>

                <div class="sfp-field">
                    <label class="sfp-label">Email</label>
                    <input type="email" name="email" class="sfp-input" value="{{ old('email') }}">
                    @error('email')
                        <span class="sfp-invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>
            </div>

            <div class="sfp-field">
                <label class="sfp-label">Family link</label>
                <input type="text" name="family_link" class="sfp-input" value="{{ old('family_link') }}" placeholder="e.g. Bridal party with Priya Nair">
                @error('family_link')
                    <span class="sfp-invalid-feedback">{{ $message }}</span>
                @enderror
            </div>

            <div class="sfp-field">
                <label class="sfp-label">Notes</label>
                <textarea name="notes" class="sfp-textarea" placeholder="Preferences, allergies, preferred stylist">{{ old('notes') }}</textarea>
                @error('notes')
                    <span class="sfp-invalid-feedback">{{ $message }}</span>
                @enderror
            </div>

            <div class="sfp-field">
                <label class="sfp-label">GSTIN (optional)</label>
                <input type="text" name="gst_number" class="sfp-input" value="{{ old('gst_number') }}" placeholder="For B2B invoices">
                @error('gst_number')
                    <span class="sfp-invalid-feedback">{{ $message }}</span>
                @enderror
            </div>

            <div class="sfp-form-actions">
                <button type="submit" class="sfp-btn-primary">Save</button>
                <a href="{{ $tenantUrl->route('clients.index') }}" class="sfp-btn-outline">Cancel</a>
            </div>
        </form>
    </div>
@endsection
