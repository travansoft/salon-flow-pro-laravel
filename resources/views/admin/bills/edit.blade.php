@extends('layouts.admin')

@section('title', 'Edit Bill #'.$bill->bill_number)

@section('content')
    <div class="sfp-page-header">
        <div>
            <h1 class="sfp-page-title">Edit bill &middot; {{ $bill->invoiceNumber() }}</h1>
            <p class="sfp-page-subtitle">
                Only the client, internal note, bill date, and the servicing and referring staff of each item can be changed here. To correct items, quantities, or amounts,
                cancel this bill and create a new one. The date can only be moved within financial year {{ $bill->financial_year }}.
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
                <label class="sfp-label" for="bill-edit-date">Bill date</label>
                <input type="date" id="bill-edit-date" name="bill_date" value="{{ old('bill_date', $bill->created_at->toDateString()) }}" class="sfp-input" max="{{ now()->toDateString() }}">
                @error('bill_date')
                    <span class="sfp-invalid-feedback">{{ $message }}</span>
                @enderror
            </div>

            <div class="sfp-field">
                <label class="sfp-label">Staff on each item</label>
                @foreach ($bill->lineItems as $lineItem)
                    <div class="sfp-split-2" style="margin-bottom:10px">
                        <div>
                            <div style="font-size:13px;color:#66736F;margin-bottom:4px">{{ $lineItem->description }} &middot; servicing staff</div>
                            <select name="items[{{ $lineItem->id }}][staff_profile_id]" class="sfp-select">
                                <option value="">Select staff&hellip;</option>
                                @foreach ($servicingOptions[$lineItem->id] as $member)
                                    <option value="{{ $member->id }}" @selected(old("items.{$lineItem->id}.staff_profile_id", $lineItem->staff_profile_id) == $member->id)>{{ $member->name }}</option>
                                @endforeach
                            </select>
                            @error("items.{$lineItem->id}.staff_profile_id")
                                <span class="sfp-invalid-feedback">{{ $message }}</span>
                            @enderror
                        </div>
                        @if(in_array($lineItem->id, $hiddenReferrerLineIds, true))
                            <input type="hidden" name="items[{{ $lineItem->id }}][referred_by_staff_profile_id]" value="{{ $lineItem->referred_by_staff_profile_id }}">
                        @else
                            <div>
                                <div style="font-size:13px;color:#66736F;margin-bottom:4px">Referred by{{ $lineItem->combo_group ? ' (whole combo)' : '' }}</div>
                                <select name="items[{{ $lineItem->id }}][referred_by_staff_profile_id]" class="sfp-select">
                                    <option value="">Direct</option>
                                    @foreach ($referrers as $member)
                                        <option value="{{ $member->id }}" @selected(old("items.{$lineItem->id}.referred_by_staff_profile_id", $lineItem->referred_by_staff_profile_id) == $member->id)>{{ $member->name }}</option>
                                    @endforeach
                                </select>
                                @error("items.{$lineItem->id}.referred_by_staff_profile_id")
                                    <span class="sfp-invalid-feedback">{{ $message }}</span>
                                @enderror
                            </div>
                        @endif
                    </div>
                    @if ($lineItem->service?->is_combo && ! $lineItem->combo_group)
                        <div style="margin:-2px 0 14px;padding:10px 12px;border:1px dashed #C9D3D0;border-radius:10px">
                            <div style="font-size:13px;color:#66736F;margin-bottom:6px">This combo was billed as one line. Select who gave each service to split it (amounts are shared in proportion to the combo's service prices). Leave blank to keep it as is.</div>
                            @foreach ($lineItem->service->comboItems as $comboItem)
                                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;align-items:center;margin-bottom:6px">
                                    <span style="font-size:13px">{{ $comboItem->component->name }}</span>
                                    <select name="combo_split[{{ $lineItem->id }}][{{ $comboItem->component_service_id }}]" class="sfp-select">
                                        <option value="">Select staff&hellip;</option>
                                        @foreach ($comboItem->component->staff->where('is_active', true) as $member)
                                            <option value="{{ $member->id }}" @selected(old("combo_split.{$lineItem->id}.{$comboItem->component_service_id}") == $member->id)>{{ $member->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endforeach
                        </div>
                    @endif
                @endforeach
                @error('combo_split')
                    <span class="sfp-invalid-feedback">{{ $message }}</span>
                @enderror
                @error('items')
                    <span class="sfp-invalid-feedback">{{ $message }}</span>
                @enderror
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
