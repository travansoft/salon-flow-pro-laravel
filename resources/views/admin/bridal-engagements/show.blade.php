@extends('layouts.admin')

@section('title', 'Engagement')

@section('content')
    @php
        $statusPills = [
            \App\Models\BridalEngagement::StatusPlanned => 'sfp-pill-blue',
            \App\Models\BridalEngagement::StatusTrialCompleted => 'sfp-pill-amber',
            \App\Models\BridalEngagement::StatusCompleted => 'sfp-pill-green',
            \App\Models\BridalEngagement::StatusCancelled => 'sfp-pill-neutral',
        ];
        $pillClass = $statusPills[$engagement->status] ?? 'sfp-pill-neutral';
        $details = [
            'Contact number' => $engagement->client->phone,
            'Event name' => $engagement->event_name,
            'Date of event' => $engagement->event_date->format('d M Y'),
            'Venue' => ucfirst($engagement->venue_type->value),
            'Home location' => $engagement->home_location,
            'Studio trial' => $engagement->has_studio_trial ? 'Yes' : 'No',
            'Date of trial' => $engagement->trial_date?->format('d M Y'),
            'Time to get ready' => $engagement->ready_time ? \Illuminate\Support\Str::substr($engagement->ready_time, 0, 5) : null,
            'Guest makeup' => $engagement->guest_makeup_count,
            'Groom makeup' => $engagement->groom_makeup ? 'Yes' : 'No',
            'Dress' => $engagement->dress_type ? ucfirst($engagement->dress_type->value) : null,
            'Saree drapist' => $engagement->saree_drapist_name,
        ];
    @endphp

    <div class="sfp-page-header">
        <div>
            <h1 class="sfp-page-title">{{ $engagement->client->name }}</h1>
            <p class="sfp-page-subtitle">{{ $engagement->event_name ?: 'Bridal engagement' }} &middot; {{ $engagement->event_date->format('d M Y') }}</p>
        </div>
        <div style="display:flex;gap:10px">
            @can('appointments.edit')
                <a href="{{ $tenantUrl->route('bridalEngagements.edit', $engagement) }}" class="sfp-btn-outline">Edit</a>
            @endcan
            <a href="{{ $tenantUrl->route('bridalEngagements.index') }}" class="sfp-btn-outline">Back to engagements</a>
        </div>
    </div>

    <div class="sfp-card">
        <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:18px">
            <div class="sfp-mono" style="font-size:11px;color:#94A19D">Engagement #{{ $engagement->id }}</div>
            <span class="sfp-pill {{ $pillClass }}">{{ ucfirst(str_replace('_', ' ', $engagement->status)) }}</span>
        </div>

        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:14px;font-size:13.5px">
            @foreach ($details as $label => $detail)
                @if ($detail !== null && $detail !== '')
                    <div>
                        <div style="font-size:11.5px;letter-spacing:.06em;text-transform:uppercase;color:#94A19D">{{ $label }}</div>
                        <div>{{ $detail }}</div>
                    </div>
                @endif
            @endforeach
        </div>

        @if ($engagement->notes)
            <div style="font-size:13px;color:#16201D;margin-top:16px">
                <strong>Note:</strong> {{ $engagement->notes }}
            </div>
        @endif
    </div>

    <div class="sfp-card" style="margin-top:14px">
        <h2 class="sfp-card-title">Bills &amp; accounts</h2>

        @if ($errors->has('bill') || $errors->has('bill_id'))
            <div class="sfp-invalid-feedback" style="margin-bottom:12px">{{ $errors->first('bill') ?: $errors->first('bill_id') }}</div>
        @endif

        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:12px;margin-bottom:18px;font-size:13.5px">
            <div><div style="font-size:11.5px;color:#94A19D">Event total</div>{{ $summary['total'] }}</div>
            <div><div style="font-size:11.5px;color:#94A19D">Advance</div>{{ $summary['advance'] }}</div>
            <div><div style="font-size:11.5px;color:#94A19D">Billed</div>{{ $summary['billed'] }}</div>
            <div><div style="font-size:11.5px;color:#94A19D">Collected</div>{{ $summary['collected'] }}</div>
            <div><div style="font-size:11.5px;color:#94A19D">Outstanding</div>{{ $summary['outstanding'] }}</div>
        </div>

        <div style="display:grid;gap:8px;margin-bottom:18px">
            @forelse ($engagement->bills as $bill)
                <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;padding:12px 15px;border-radius:13px;background:#F3F6F5;border:1px solid #F0E7E1;font-size:13.5px">
                    <div>
                        <a href="{{ $tenantUrl->route('bills.show', $bill) }}" style="color:#1B4B8F">{{ $bill->invoiceNumber() }}</a>
                        <span style="color:#66736F">&middot; Total {{ number_format((float) $bill->total, 2) }} &middot; Paid {{ number_format((float) $bill->amount_paid, 2) }}</span>
                    </div>
                    <div style="display:flex;align-items:center;gap:10px">
                        <span class="sfp-pill sfp-pill-neutral">{{ ucfirst($bill->status) }}</span>
                        @can('billing.create')
                            <form action="{{ $tenantUrl->route('bridalEngagements.bills.detach', ['bridalEngagement' => $engagement, 'bill' => $bill]) }}" method="POST">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="sfp-btn-outline">Unlink</button>
                            </form>
                        @endcan
                    </div>
                </div>
            @empty
                <p style="color:#66736F;margin:0">No bills attached yet.</p>
            @endforelse
        </div>

        @can('billing.create')
            <div style="display:flex;flex-wrap:wrap;gap:10px">
                <button type="button" class="sfp-btn-primary" data-bs-toggle="modal" data-bs-target="#createEventBillModal">Create bill for event</button>
                <button type="button" class="sfp-btn-outline" data-bs-toggle="modal" data-bs-target="#attachEventBillModal">Attach existing bill</button>
            </div>
        @endcan
    </div>

    @include('admin.bridal-engagements.partials.bill-modals')
@endsection
