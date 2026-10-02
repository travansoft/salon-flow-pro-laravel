@extends('layouts.admin')

@section('title', 'Bonuses')

@section('content')
    <div class="sfp-page-header">
        <div>
            <h1 class="sfp-page-title">Bonuses</h1>
            <p class="sfp-page-subtitle">
                {{ $month->format('F Y') }} &middot; {{ $bonuses->count() }} {{ $bonuses->count() === 1 ? 'entry' : 'entries' }}
                &middot; Total <span class="sfp-mono">&#8377;{{ number_format((float) $total, 2) }}</span>
            </p>
        </div>
        <form method="GET" class="sfp-row">
            <input type="month" name="month" class="sfp-input" value="{{ $month->format('Y-m') }}">
            @if($staff->isNotEmpty())
                <select name="staff_profile_id" class="sfp-select">
                    <option value="">All staff</option>
                    @foreach ($staff as $member)
                        <option value="{{ $member->id }}" @selected($staffProfileId === $member->id)>{{ $member->name }}</option>
                    @endforeach
                </select>
            @endif
            <button type="submit" class="sfp-btn-primary">Show</button>
        </form>
    </div>

    @include('admin.incentive._nav')

    <div class="sfp-table-wrap">
        <div class="sfp-table-head-row" style="grid-template-columns:110px 1.1fr 1.6fr 120px 1fr 130px">
            <span>Date</span>
            <span>Staff</span>
            <span>Reason</span>
            <span>Amount</span>
            <span>Awarded by</span>
            <span></span>
        </div>

        @forelse ($bonuses as $bonus)
            <div class="sfp-table-row" style="grid-template-columns:110px 1.1fr 1.6fr 120px 1fr 130px">
                <span class="sfp-mono">{{ $bonus->awarded_date->format('d M Y') }}</span>
                <span>{{ $bonus->staffProfile?->name ?? '—' }}</span>
                <span>{{ $bonus->reason }}</span>
                <span class="sfp-mono" style="font-weight:600">&#8377;{{ number_format((float) $bonus->amount, 2) }}</span>
                <span style="color:#66736F">{{ $bonus->awardedBy?->name ?? '—' }}</span>
                <div class="sfp-row" style="gap:10px">
                    @can('incentives.edit')
                        <a href="{{ $tenantUrl->route('staffBonuses.edit', $bonus) }}" style="font-size:12.5px;color:#1B4B8F">Edit</a>
                    @endcan
                    @can('incentives.delete')
                        <form action="{{ $tenantUrl->route('staffBonuses.destroy', $bonus) }}" method="POST" style="margin:0" onsubmit="return confirm('Delete this bonus?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="sfp-btn-link-danger">Delete</button>
                        </form>
                    @endcan
                </div>
            </div>
        @empty
            <div class="sfp-table-row" style="grid-template-columns:1fr">
                <span style="color:#66736F">No bonuses for this selection.</span>
            </div>
        @endforelse
    </div>
@endsection
