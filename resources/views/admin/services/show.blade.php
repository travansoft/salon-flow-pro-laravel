@extends('layouts.admin')

@section('title', $service->name)

@section('content')
    <div class="sfp-page-header">
        <div>
            <h1 class="sfp-page-title">{{ $service->name }}</h1>
            <p class="sfp-page-subtitle">
                {{ $service->category?->name ?: 'Uncategorised' }}
                @if ($service->code)
                    &middot; Code <span class="sfp-mono">{{ $service->code }}</span>
                @endif
            </p>
        </div>
        @can('services.edit')
            <a href="{{ $tenantUrl->route('services.edit', $service) }}" class="sfp-btn-outline">Edit service</a>
        @endcan
    </div>

    <div class="sfp-card" style="margin-bottom:16px">
        <div style="display:flex;align-items:center;gap:28px;flex-wrap:wrap">
            <div>
                <div class="sfp-label" style="margin-bottom:6px">Price (incl. GST)</div>
                <div class="sfp-mono" style="font-size:20px">&#8377;{{ number_format($service->priceInclusiveOfTax((float) $tenant->default_gst_rate), 2) }}</div>
                <div style="font-size:11.5px;color:#94A19D;margin-top:2px">&#8377;{{ number_format($service->price, 2) }} + {{ $service->effectiveTaxRate((float) $tenant->default_gst_rate) }}% GST</div>
            </div>
            <div>
                <div class="sfp-label" style="margin-bottom:6px">Duration</div>
                <div class="sfp-mono" style="font-size:20px">{{ $service->duration_minutes }} min</div>
            </div>
            <div>
                <div class="sfp-label" style="margin-bottom:6px">Status</div>
                @if ($service->is_active)
                    <span class="sfp-pill sfp-pill-green">Active</span>
                @else
                    <span class="sfp-pill sfp-pill-neutral">Disabled</span>
                @endif
            </div>
        </div>
    </div>

    <h2 class="sfp-card-title">Price history</h2>
    <div class="sfp-table-wrap">
        <div class="sfp-table-head-row" style="grid-template-columns:1fr 1fr 1fr">
            <span>Price (excl. GST)</span>
            <span>Effective from</span>
            <span>Changed by</span>
        </div>
        @forelse ($service->priceHistories()->latest('effective_from')->get() as $history)
            <div class="sfp-table-row" style="grid-template-columns:1fr 1fr 1fr">
                <span class="sfp-mono" style="font-size:13px">&#8377;{{ number_format($history->price, 2) }}</span>
                <span style="font-size:13.5px;color:#66736F">{{ $history->effective_from->format('d M Y, H:i') }}</span>
                <span style="font-size:13.5px;color:#66736F">{{ $history->changedBy?->name ?? '—' }}</span>
            </div>
        @empty
            <div class="sfp-table-row" style="grid-template-columns:1fr">
                <span style="color:#94A19D;font-size:13.5px">No price changes recorded.</span>
            </div>
        @endforelse
    </div>
@endsection
