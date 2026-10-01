@extends('layouts.admin')

@section('title', 'Incentive Credit')

@section('content')
    <div class="sfp-page-header">
        <div>
            <h1 class="sfp-page-title">{{ $staff->name }}</h1>
            <p class="sfp-page-subtitle">
                Credit lines for {{ $month->format('F Y') }}.
                @if ($progress)
                    Achieved <span class="sfp-mono">&#8377;{{ number_format((float) $progress['achieved'], 2) }}</span>
                @endif
            </p>
        </div>
        <a href="{{ $tenantUrl->route('incentiveProgress.index') }}?month={{ $month->format('Y-m') }}" class="sfp-btn-outline">Back to progress</a>
    </div>

    <div class="sfp-table-wrap">
        <div class="sfp-table-head-row" style="grid-template-columns:110px 100px 1.4fr 100px 1fr 120px 130px">
            <span>Bill</span>
            <span>Date</span>
            <span>Item</span>
            <span>Role</span>
            <span>With</span>
            <span>Value</span>
            <span>Credit</span>
        </div>

        @forelse ($creditLines as $row)
            <div class="sfp-table-row" style="grid-template-columns:110px 100px 1.4fr 100px 1fr 120px 130px">
                @can('billing.view')
                    <a href="{{ $tenantUrl->route('bills.show', $row['bill']) }}" style="color:#1B4B8F">{{ $row['bill']->invoiceNumber() }}</a>
                @else
                    <span>{{ $row['bill']->invoiceNumber() }}</span>
                @endcan
                <span class="sfp-mono">{{ $row['bill']->created_at->format('d M Y') }}</span>
                <div>
                    <div>{{ $row['lineItem']->description }}</div>
                    @if ($row['bill']->amount_refunded > 0)
                        <div style="font-size:11.5px;color:#A8506B">Refunded &#8377;{{ number_format($row['bill']->amount_refunded, 2) }} on this bill</div>
                    @endif
                </div>
                <span>{{ $row['role'] === 'servicing' ? 'Servicing' : 'Referral' }} {{ number_format((float) $row['percent'], 0) }}%</span>
                <span>{{ $row['otherStaff']?->name ?? '—' }}</span>
                <span class="sfp-mono">&#8377;{{ number_format((float) $row['basis'], 2) }}</span>
                <span class="sfp-mono" style="font-weight:600">&#8377;{{ number_format((float) $row['credit'], 2) }}</span>
            </div>
        @empty
            <div class="sfp-table-row">
                <span style="color:#66736F">No credited bills for this month.</span>
            </div>
        @endforelse
    </div>
@endsection
