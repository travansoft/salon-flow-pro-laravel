@extends('layouts.admin')

@section('title', 'Billing')

@section('content')
    <div class="sfp-page-header">
        <div>
            <h1 class="sfp-page-title">Billing</h1>
            <p class="sfp-page-subtitle">Bills generated on {{ $date }}.</p>
        </div>
        <div class="sfp-row">
            <form method="GET" style="margin:0">
                <input type="date" name="date" value="{{ $date }}" class="sfp-input" style="margin-bottom:0;max-width:200px" onchange="this.form.submit()">
            </form>
            @can('billing.create')
                <a href="{{ $tenantUrl->route('bills.create') }}" class="sfp-btn-primary">New bill</a>
            @endcan
        </div>
    </div>

    <div class="sfp-table-wrap">
        <div class="sfp-table-head-row" style="grid-template-columns:1fr 1fr 1fr 120px 120px 110px 130px">
            <span>Bill #</span>
            <span>Client</span>
            <span>Billed by</span>
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
            <div class="sfp-table-row" style="grid-template-columns:1fr 1fr 1fr 120px 120px 110px 130px">
                <span class="sfp-mono" style="font-size:13.5px">{{ $bill->bill_number }}</span>
                <span style="font-size:14px">{{ $bill->client->name }}</span>
                <span style="font-size:13px;color:#66736F">{{ $bill->createdBy->name ?? '—' }}</span>
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
                <p style="color:#66736F;margin:0">No bills for this date.</p>
            </div>
        @endforelse
    </div>
@endsection
