@extends('layouts.admin')

@section('title', 'Edit Bill #'.$bill->bill_number)

@section('content')
    <div class="sfp-page-header">
        <div>
            <h1 class="sfp-page-title">Edit bill &middot; {{ $bill->invoiceNumber() }}</h1>
            <p class="sfp-page-subtitle">
                Only the client and internal note can be changed here. To correct items, quantities, or amounts,
                cancel this bill and create a new one.
            </p>
        </div>
    </div>

    <div class="sfp-card">
        <form action="{{ $tenantUrl->route('bills.update', $bill) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="sfp-field">
                <label class="sfp-label" for="bill-edit-client-name">Client name</label>
                <input type="text" id="bill-edit-client-name" name="client_name" value="{{ old('client_name', $bill->client->name) }}" class="sfp-input" autocomplete="off">
                <input type="hidden" name="client_id" value="{{ old('client_id') }}">
                @error('client_name')
                    <span class="sfp-invalid-feedback">{{ $message }}</span>
                @enderror
            </div>

            <div class="sfp-split-2">
                <div class="sfp-field">
                    <label class="sfp-label" for="bill-edit-client-phone">Mobile number <span style="color:#94A19D;font-weight:400">(optional)</span></label>
                    <input type="text" id="bill-edit-client-phone" name="client_phone" value="{{ old('client_phone', $bill->client->phone) }}" class="sfp-input" autocomplete="off">
                    @error('client_phone')
                        <span class="sfp-invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>

                <div class="sfp-field">
                    <label class="sfp-label" for="bill-edit-client-gst">Client GSTIN <span style="color:#94A19D;font-weight:400">(optional, for a GST invoice)</span></label>
                    <input type="text" id="bill-edit-client-gst" name="client_gst_number" value="{{ old('client_gst_number', $bill->client->gst_number) }}" class="sfp-input" autocomplete="off">
                    @error('client_gst_number')
                        <span class="sfp-invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>
            </div>

            <div class="sfp-field">
                <label class="sfp-label" for="bill-edit-notes">Note <span style="color:#94A19D;font-weight:400">(internal, not printed)</span></label>
                <textarea id="bill-edit-notes" name="notes" class="sfp-input" rows="4" maxlength="2000">{{ old('notes', $bill->notes) }}</textarea>
                @error('notes')
                    <span class="sfp-invalid-feedback">{{ $message }}</span>
                @enderror
            </div>

            <div class="sfp-form-actions">
                <button type="submit" class="sfp-btn-primary">Save changes</button>
                <a href="{{ $tenantUrl->route('bills.show', $bill) }}" class="sfp-btn-outline">Cancel</a>
            </div>
        </form>
    </div>
@endsection
