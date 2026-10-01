@extends('layouts.admin')

@section('title', 'Day book')

@section('content')
    <div class="sfp-page-header">
        <div>
            <h1 class="sfp-page-title">Day book</h1>
            <p class="sfp-page-subtitle">Money in from bills, money out to refunds and expenses.</p>
        </div>
        <form method="GET" action="{{ $tenantUrl->route('reports.dayBook') }}" style="display:flex;gap:8px;align-items:center">
            <input type="date" name="from" class="sfp-input" value="{{ $from->format('Y-m-d') }}">
            <span style="color:#94A19D">to</span>
            <input type="date" name="to" class="sfp-input" value="{{ $to->format('Y-m-d') }}">
            <button type="submit" class="sfp-btn-primary">Show</button>
        </form>
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:12px;margin-bottom:14px">
        @foreach (['cash' => 'Cash', 'upi' => 'UPI'] as $method => $label)
            <div class="sfp-card">
                <div class="sfp-label" style="margin-bottom:12px">Closing {{ $label }}</div>
                <div class="sfp-heading" style="font-size:32px;line-height:1">&#8377;{{ number_format((float) $closing[$method], 2) }}</div>
                <div style="margin-top:12px;font-size:12.5px;color:#66736F;display:grid;gap:3px">
                    <div style="display:flex;justify-content:space-between"><span>Opening</span><span class="sfp-mono">&#8377;{{ number_format((float) $opening[$method], 2) }}</span></div>
                    <div style="display:flex;justify-content:space-between"><span>In</span><span class="sfp-mono" style="color:#2F6849">+&#8377;{{ number_format((float) $totals[$method]['in'], 2) }}</span></div>
                    <div style="display:flex;justify-content:space-between"><span>Out</span><span class="sfp-mono" style="color:#A8506B">&minus;&#8377;{{ number_format((float) $totals[$method]['out'], 2) }}</span></div>
                </div>
            </div>
        @endforeach
        <div class="sfp-card">
            <div class="sfp-label" style="margin-bottom:12px">Card</div>
            <div style="font-size:12.5px;color:#66736F;display:grid;gap:3px">
                <div style="display:flex;justify-content:space-between"><span>In</span><span class="sfp-mono" style="color:#2F6849">+&#8377;{{ number_format((float) $totals['card']['in'], 2) }}</span></div>
                <div style="display:flex;justify-content:space-between"><span>Out</span><span class="sfp-mono" style="color:#A8506B">&minus;&#8377;{{ number_format((float) $totals['card']['out'], 2) }}</span></div>
            </div>
            <p style="color:#94A19D;font-size:12px;margin:12px 0 0">Not part of cash or UPI closing.</p>
        </div>
    </div>

    <div class="sfp-card" style="padding:0;overflow:hidden">
        <div style="display:grid;grid-template-columns:.9fr .8fr 1.2fr 1.6fr .6fr 1fr 1fr;padding:14px 20px;background:#F8FAF9;border-bottom:1px solid #E3EAE8;font-size:11.5px;letter-spacing:.06em;text-transform:uppercase;color:#94A19D">
            <span>Date</span><span>Type</span><span>Reference</span><span>Details</span><span>Mode</span><span style="text-align:right">In</span><span style="text-align:right">Out</span>
        </div>
        @forelse ($entries as $entry)
            <div style="display:grid;grid-template-columns:.9fr .8fr 1.2fr 1.6fr .6fr 1fr 1fr;padding:13px 20px;border-bottom:1px solid #EDF1F0;align-items:center;font-size:13.5px">
                <span style="color:#66736F">{{ $entry['date']->format('d M Y') }}</span>
                <span>{{ $entry['source'] }}</span>
                <span class="sfp-mono" style="font-size:12.5px">
                    @if($entry['bill_id'])
                        <a href="{{ $tenantUrl->route('bills.show', ['bill' => $entry['bill_id']]) }}" class="sfp-action-link">{{ $entry['reference'] }}</a>
                    @else
                        {{ $entry['reference'] }}
                    @endif
                </span>
                <span>{{ $entry['description'] }}</span>
                <span style="text-transform:uppercase;font-size:12px;color:#66736F">{{ $entry['method'] }}</span>
                <span class="sfp-mono" style="text-align:right;color:#2F6849">@if($entry['type'] === 'in')&#8377;{{ number_format((float) $entry['amount'], 2) }}@endif</span>
                <span class="sfp-mono" style="text-align:right;color:#A8506B">@if($entry['type'] === 'out')&#8377;{{ number_format((float) $entry['amount'], 2) }}@endif</span>
            </div>
        @empty
            <div style="padding:20px;color:#94A19D;font-size:13.5px">No transactions in this period.</div>
        @endforelse
    </div>
@endsection
