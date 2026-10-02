@extends('layouts.admin')

@section('title', 'Edit Bonus')

@section('content')
    <div class="sfp-page-header">
        <div>
            <h1 class="sfp-page-title">Edit bonus</h1>
            <p class="sfp-page-subtitle">Changes apply to the month of the awarded date.</p>
        </div>
    </div>

    <div class="sfp-card">
        <form action="{{ $tenantUrl->route('staffBonuses.update', $bonus) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="sfp-field">
                <label class="sfp-label">Staff member</label>
                <select name="staff_profile_id" class="sfp-select">
                    @foreach ($staff as $member)
                        <option value="{{ $member->id }}" @selected(old('staff_profile_id', $bonus->staff_profile_id) == $member->id)>{{ $member->name }}</option>
                    @endforeach
                </select>
                @error('staff_profile_id')
                    <span class="sfp-invalid-feedback">{{ $message }}</span>
                @enderror
            </div>

            <div class="sfp-split-2">
                <div class="sfp-field">
                    <label class="sfp-label">Amount</label>
                    <input type="number" step="0.01" name="amount" class="sfp-input" value="{{ old('amount', $bonus->amount) }}">
                    @error('amount')
                        <span class="sfp-invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>

                <div class="sfp-field">
                    <label class="sfp-label">Awarded date</label>
                    <input type="date" name="awarded_date" class="sfp-input" value="{{ old('awarded_date', $bonus->awarded_date->format('Y-m-d')) }}">
                    @error('awarded_date')
                        <span class="sfp-invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>
            </div>

            <div class="sfp-field">
                <label class="sfp-label">Reason</label>
                <input type="text" name="reason" class="sfp-input" value="{{ old('reason', $bonus->reason) }}">
                @error('reason')
                    <span class="sfp-invalid-feedback">{{ $message }}</span>
                @enderror
            </div>

            <div class="sfp-form-actions">
                <button type="submit" class="sfp-btn-primary">Save changes</button>
                <a href="{{ $tenantUrl->route('staffBonuses.index') }}?month={{ $bonus->awarded_date->format('Y-m') }}" class="sfp-btn-outline">Cancel</a>
            </div>
        </form>
    </div>
@endsection
