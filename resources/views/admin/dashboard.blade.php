@extends('layouts.admin')

@section('title', 'Dashboard')

@section('content')
    <div class="sfp-page-header">
        <div>
            <h1 class="sfp-page-title">Good morning, {{ auth()->user()->name }}</h1>
            <p class="sfp-page-subtitle">Here's how {{ $tenant->name ?? 'your studio' }} is doing today.</p>
        </div>
    </div>

    @include('admin.bills.partials.drafts', ['drafts' => $drafts])

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(178px,1fr));gap:12px">
        <div class="sfp-card">
            <div class="sfp-label" style="margin-bottom:12px">Today's Revenue</div>
            <div class="sfp-heading" style="font-size:34px;line-height:1">&#8377;{{ number_format((float) $todaysRevenue, 2) }}</div>
        </div>

        <div class="sfp-card">
            <div class="sfp-label" style="margin-bottom:12px">Customers</div>
            <div class="sfp-heading" style="font-size:34px;line-height:1">{{ $customerCount }}</div>
        </div>

        <div class="sfp-card">
            <div class="sfp-label" style="margin-bottom:12px">Average Bill</div>
            <div class="sfp-heading" style="font-size:34px;line-height:1">&#8377;{{ number_format((float) $averageBill, 2) }}</div>
        </div>

        <div class="sfp-card">
            <div class="sfp-label" style="margin-bottom:12px">Top Employee</div>
            @if ($topEmployee)
                <div class="sfp-heading" style="font-size:22px;line-height:1.2">{{ $topEmployee['name'] }}</div>
                <div class="sfp-mono" style="font-size:13px;color:#66736F;margin-top:8px">&#8377;{{ number_format((float) $topEmployee['revenue'], 2) }}</div>
            @else
                <div class="sfp-heading" style="font-size:22px;line-height:1.2;color:#94A19D">&mdash;</div>
            @endif
        </div>

        <div class="sfp-card">
            <div class="sfp-label" style="margin-bottom:12px">Top Service</div>
            <div class="sfp-heading" style="font-size:22px;line-height:1.2">{{ $topService ?? '—' }}</div>
        </div>

        <div class="sfp-card">
            <div class="sfp-label" style="margin-bottom:12px">Pending Payments</div>
            <div class="sfp-heading" style="font-size:34px;line-height:1">&#8377;{{ number_format((float) $pendingPayments, 2) }}</div>
        </div>

        <div class="sfp-card">
            <div class="sfp-label" style="margin-bottom:12px">Low Stock</div>
            @if ($lowStockCount > 0)
                <div class="sfp-heading" style="font-size:34px;line-height:1;color:#A8506B">{{ $lowStockCount }}</div>
                <div style="font-size:12.5px;color:#94A19D;margin-top:8px">{{ Str::plural('item', $lowStockCount) }} at or below reorder level</div>
            @else
                <div class="sfp-heading" style="font-size:22px;line-height:1.2;color:#2F6849">All stocked</div>
            @endif
        </div>

        <div class="sfp-card">
            <div class="sfp-label" style="margin-bottom:12px">This Month</div>
            @if ($monthRevenueChangePercent === null)
                <div class="sfp-heading" style="font-size:22px;line-height:1.2;color:#94A19D">No prior data</div>
            @else
                <div class="sfp-heading" style="font-size:28px;line-height:1;color:{{ $monthRevenueChangePercent >= 0 ? '#2F6849' : '#A8506B' }}">
                    Revenue {{ $monthRevenueChangePercent >= 0 ? '↑' : '↓' }} {{ number_format(abs($monthRevenueChangePercent), 0) }}%
                </div>
            @endif
        </div>
    </div>
@endsection
