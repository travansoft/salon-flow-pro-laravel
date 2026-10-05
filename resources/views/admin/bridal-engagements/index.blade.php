@extends('layouts.admin')

@section('title', 'Bridal & On-Site Events')

@section('content')
    <div class="sfp-page-header">
        <div>
            <h1 class="sfp-page-title">Bridal &amp; events</h1>
            <p class="sfp-page-subtitle">{{ $engagements->count() }} live events</p>
        </div>
        @can('appointments.create')
            <a href="{{ $tenantUrl->route('bridalEngagements.create') }}" class="sfp-btn-pill-dark">+ New event</a>
        @endcan
    </div>

    @php
        $statusPills = [
            \App\Models\BridalEngagement::StatusPlanned => 'sfp-pill-blue',
            \App\Models\BridalEngagement::StatusTrialCompleted => 'sfp-pill-amber',
            \App\Models\BridalEngagement::StatusCompleted => 'sfp-pill-green',
            \App\Models\BridalEngagement::StatusCancelled => 'sfp-pill-neutral',
        ];
    @endphp

    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(340px,1fr));gap:12px">
        @forelse ($engagements as $engagement)
            @php
                $pillClass = $statusPills[$engagement->status] ?? 'sfp-pill-neutral';
            @endphp
            <div class="sfp-card">
                <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:18px">
                    <div>
                        <div class="sfp-heading" style="font-weight:600;font-size:23px;letter-spacing:-.01em">{{ $engagement->client->name }}</div>
                        <div class="sfp-mono" style="font-size:11px;color:#94A19D;margin-top:4px">
                            {{ $engagement->event_name ?: "Event #{$engagement->id}" }} &middot; {{ $engagement->event_date->format('d M Y') }}
                        </div>
                    </div>
                    <span class="sfp-pill sfp-pill-sage">{{ ucfirst($engagement->venue_type->value) }}</span>
                </div>

                <div style="display:grid;gap:8px;margin-bottom:16px">
                    <div style="display:flex;gap:12px;padding:13px 15px;border-radius:13px;background:#F3F6F5;border:1px solid #F0E7E1">
                        <span class="sfp-mono" style="font-size:10px;color:#2E5F4C;width:42px;flex:none;padding-top:3px">READY</span>
                        <div style="font-size:13px;line-height:1.5">
                            {{ $engagement->readyTimeLabel() ?? 'Not set' }}
                            @if ($engagement->has_studio_trial && $engagement->trial_date)
                                &middot; Trial {{ $engagement->trial_date->format('d M Y') }}
                            @endif
                        </div>
                    </div>
                    <div style="display:flex;gap:12px;padding:13px 15px;border-radius:13px;background:#E2EDE7;border:1px solid #D8E8E0">
                        <span class="sfp-mono" style="font-size:10px;color:#2E5F4C;width:42px;flex:none;padding-top:3px">AMOUNT</span>
                        <div style="font-size:13px;line-height:1.5">
                            Total {{ number_format((float) $engagement->total_amount, 2) }}
                            &middot; Advance {{ number_format((float) $engagement->advance_amount, 2) }}
                            &middot; Balance {{ number_format((float) $engagement->balanceAmount(), 2) }}
                            &middot; Billed {{ number_format((float) $engagement->billedAmount(), 2) }}
                        </div>
                    </div>
                </div>

                <div style="display:flex;align-items:center;justify-content:space-between;padding-top:14px;border-top:1px solid #EDF1F0">
                    <div style="font-size:12.5px;color:#66736F">
                        {{ $engagement->bills->count() }} {{ \Illuminate\Support\Str::plural('bill', $engagement->bills->count()) }}
                    </div>
                    <div style="display:flex;align-items:center;gap:12px">
                        <span class="sfp-pill {{ $pillClass }}">{{ ucfirst(str_replace('_', ' ', $engagement->status)) }}</span>
                        @can('appointments.edit')
                            <a href="{{ $tenantUrl->route('bridalEngagements.edit', $engagement) }}" style="font-size:12.5px;color:#1B4B8F">Edit</a>
                        @endcan
                        <a href="{{ $tenantUrl->route('bridalEngagements.show', $engagement) }}" style="font-size:12.5px;color:#1B4B8F">View</a>
                    </div>
                </div>
            </div>
        @empty
            <div class="sfp-card">
                <p style="color:#66736F;margin:0">No events yet.</p>
            </div>
        @endforelse
    </div>
@endsection
