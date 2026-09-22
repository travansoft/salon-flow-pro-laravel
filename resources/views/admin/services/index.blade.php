@extends('layouts.admin')

@section('title', 'Services')

@section('content')
    <div class="sfp-page-header">
        <div>
            <h1 class="sfp-page-title">Service catalogue</h1>
            <p class="sfp-page-subtitle">Prices here flow into booking and billing. Past bills keep the price charged at the time.</p>
        </div>
        <div class="sfp-row">
            @can('services.view')
                <a href="{{ $tenantUrl->route('serviceCategories.index') }}" class="sfp-btn-outline">Manage categories</a>
            @endcan
            @can('services.create')
                <a href="{{ $tenantUrl->route('services.create') }}" class="sfp-btn-pill-dark">+ Add service</a>
            @endcan
        </div>
    </div>

    <div style="display:grid;gap:12px">
        @forelse ($services->groupBy(fn ($service) => $service->category?->name ?: 'Uncategorised') as $category => $categoryServices)
            <div class="sfp-table-wrap">
                <div class="sfp-table-head-row" style="grid-template-columns:1fr auto;text-transform:none;background:#FDFAF8">
                    <span style="font-size:15px;font-weight:500;color:#16201D;letter-spacing:normal">{{ $category }}</span>
                    <span class="sfp-mono" style="font-size:11.5px;color:#94A19D">{{ $categoryServices->count() }} services</span>
                </div>

                @foreach ($categoryServices as $service)
                    <div class="sfp-table-row" style="grid-template-columns:70px 1fr 96px 96px 140px">
                        <span class="sfp-mono" style="font-size:12.5px;color:#94A19D">{{ $service->code ?? '—' }}</span>
                        <div>
                            <div style="font-size:14.5px">{{ $service->name }}</div>
                        </div>
                        <span class="sfp-mono" style="font-size:13px;color:#66736F;text-align:right">{{ $service->duration_minutes }} min</span>
                        <span class="sfp-mono" style="font-size:14px;text-align:right">&#8377;{{ number_format($service->priceInclusiveOfTax((float) $tenant->default_gst_rate), 2) }}</span>
                        <div style="display:flex;align-items:center;justify-content:flex-end;gap:12px">
                            @if (! $service->is_active)
                                <span class="sfp-pill sfp-pill-neutral">Disabled</span>
                            @endif
                            <a href="{{ $tenantUrl->route('services.show', $service) }}" style="font-size:12.5px;color:#66736F">View</a>
                            @can('services.edit')
                                <a href="{{ $tenantUrl->route('services.edit', $service) }}" style="font-size:12.5px;color:#1B4B8F">Edit</a>
                            @endcan
                            @can('services.delete')
                                <form action="{{ $tenantUrl->route('services.destroy', $service) }}" method="POST" style="display:inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="sfp-btn-link-danger">Disable</button>
                                </form>
                            @endcan
                        </div>
                    </div>
                @endforeach
            </div>
        @empty
            <div class="sfp-card">
                <p style="color:#66736F;margin:0">No services yet.</p>
            </div>
        @endforelse
    </div>
@endsection
