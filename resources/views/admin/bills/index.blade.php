@extends('layouts.admin')

@section('title', 'Billing')

@section('content')
    <div class="sfp-page-header">
        <div>
            <h1 class="sfp-page-title">Billing</h1>
            <p class="sfp-page-subtitle">Bills from {{ $fromDate }} to {{ $toDate }}.</p>
        </div>
        <div class="sfp-row">
            @can('billing.backfill')
                <a href="{{ $tenantUrl->route('bills.backfillCreate') }}" class="sfp-btn-outline">Backfill old bill</a>
            @endcan
            @can('billing.create')
                <a href="{{ $tenantUrl->route('bills.create') }}" class="sfp-btn-primary">New bill</a>
            @endcan
        </div>
    </div>

    <form method="GET" class="sfp-card" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;margin-bottom:16px">
        <div class="sfp-field" style="margin-bottom:0">
            <label class="sfp-label" for="bills-from-date">From date</label>
            <input type="date" id="bills-from-date" name="from_date" value="{{ $fromDate }}" class="sfp-input" style="margin-bottom:0">
        </div>
        <div class="sfp-field" style="margin-bottom:0">
            <label class="sfp-label" for="bills-to-date">To date</label>
            <input type="date" id="bills-to-date" name="to_date" value="{{ $toDate }}" class="sfp-input" style="margin-bottom:0">
        </div>
        <div class="sfp-field" style="margin-bottom:0">
            <label class="sfp-label" for="bills-client-name">Client name</label>
            <input type="text" id="bills-client-name" name="client_name" value="{{ $clientName }}" class="sfp-input" style="margin-bottom:0" autocomplete="off" placeholder="Search by name">
        </div>
        <div class="sfp-field" style="margin-bottom:0">
            <label class="sfp-label" for="bills-client-phone">Mobile number</label>
            <input type="text" id="bills-client-phone" name="client_phone" value="{{ $clientPhone }}" class="sfp-input" style="margin-bottom:0" autocomplete="off" placeholder="Search by mobile">
        </div>
        <button type="submit" class="sfp-btn-primary">Filter</button>
        <a href="{{ $tenantUrl->route('bills.index') }}" class="sfp-btn-outline">Reset</a>
    </form>

    <div class="sfp-table-wrap">
        <div class="sfp-table-head-row" style="grid-template-columns:1fr 1fr 1fr 150px 120px 120px 110px 130px">
            <span>Bill #</span>
            <span>Client</span>
            <span>Billed by</span>
            <span>Date &amp; time</span>
            <span>Total</span>
            <span>Paid</span>
            <span>Status</span>
            <span></span>
        </div>

        @forelse ($bills as $bill)
            @php
                $statusPillClass = match ($bill->status) {
                    \App\Models\Bill::StatusPaid => 'sfp-pill-green',
                    \App\Models\Bill::StatusPartial => 'sfp-pill-amber',
                    \App\Models\Bill::StatusUnpaid => 'sfp-pill-blue',
                    \App\Models\Bill::StatusVoid => 'sfp-pill-neutral',
                    default => 'sfp-pill-neutral',
                };
            @endphp
            <div class="sfp-table-row" style="grid-template-columns:1fr 1fr 1fr 150px 120px 120px 110px 130px">
                <span class="sfp-mono" style="font-size:13.5px">{{ $bill->bill_number }}</span>
                <span style="font-size:14px">{{ $bill->client->name }}</span>
                <span style="font-size:13px;color:#66736F">{{ $bill->createdBy->name ?? '—' }}</span>
                <span style="font-size:13px;color:#66736F">{{ $bill->created_at->format('d M Y, h:i A') }}</span>
                <span class="sfp-mono" style="font-size:13.5px">&#8377;{{ number_format($bill->total, 2) }}</span>
                <span class="sfp-mono" style="font-size:13.5px;color:#66736F">&#8377;{{ number_format($bill->amount_paid, 2) }}</span>
                <span class="sfp-pill {{ $statusPillClass }}">{{ ucfirst($bill->status) }}</span>
                <span style="display:flex;gap:10px">
                    <a href="{{ $tenantUrl->route('bills.show', $bill) }}" style="font-size:12.5px;color:#1B4B8F">View</a>
                    <a href="{{ $tenantUrl->route('bills.print', $bill) }}" target="_blank" style="font-size:12.5px;color:#1B4B8F">Reprint</a>
                </span>
            </div>
        @empty
            <div class="sfp-table-row" style="grid-template-columns:1fr">
                <p style="color:#66736F;margin:0">No bills match the selected filters.</p>
            </div>
        @endforelse
    </div>
@endsection
