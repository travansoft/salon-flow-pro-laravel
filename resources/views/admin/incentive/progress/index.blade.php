@extends('layouts.admin')

@section('title', 'Incentive Progress')

@section('content')
    <div class="sfp-page-header">
        <div>
            <h1 class="sfp-page-title">Incentive progress</h1>
            <p class="sfp-page-subtitle">
                {{ $month->format('F Y') }}. Credit comes from paid bills, including GST, net of discount.
                Referred services are shared {{ number_format($settings->servicing_share_percent, 0) }}% servicing / {{ number_format($settings->referring_share_percent, 0) }}% referring.
            </p>
        </div>
        <form method="GET" class="sfp-row">
            <input type="month" name="month" class="sfp-input" value="{{ $month->format('Y-m') }}">
            <button type="submit" class="sfp-btn-primary">Show</button>
        </form>
    </div>

    @include('admin.incentive._nav')

    <div class="sfp-card" style="margin-bottom:14px">
        <div class="sfp-row" style="flex-wrap:wrap;gap:18px;font-size:13px;color:#66736F">
            <strong style="color:#1F2A27">Slabs</strong>
            @forelse ($slabs as $slab)
                <span>{{ number_format($slab->min_achievement_percent, 0) }}% achieved &rarr; <strong class="sfp-mono">{{ number_format($slab->incentive_percent, 2) }}%</strong></span>
            @empty
                <span>No slabs configured.</span>
            @endforelse
        </div>
    </div>

    <div class="sfp-table-wrap">
        <div class="sfp-table-head-row" style="grid-template-columns:1.1fr 110px 130px 1.4fr 110px 130px 110px 100px 120px">
            <span>Staff</span>
            <span>Target</span>
            <span>Achieved</span>
            <span>Progress</span>
            <span>Slab</span>
            <span>To next slab</span>
            <span>Incentive</span>
            <span>Bonus</span>
            <span>Total earned</span>
        </div>

        @forelse ($progress as $row)
            <div class="sfp-table-row" style="grid-template-columns:1.1fr 110px 130px 1.4fr 110px 130px 110px 100px 120px">
                <span>{{ $row['staff']->name }}</span>
                <span class="sfp-mono">
                    @if ($row['target'] !== null)
                        &#8377;{{ number_format((float) $row['target'], 2) }}
                    @else
                        <span style="color:#94A19D">Not set</span>
                    @endif
                </span>
                <div>
                    <div class="sfp-mono" style="font-weight:600">&#8377;{{ number_format((float) $row['achieved'], 2) }}</div>
                    <div style="font-size:11px;color:#94A19D">
                        Own &#8377;{{ number_format((float) $row['servicingCredit'], 2) }}
                        &middot; Referral &#8377;{{ number_format((float) $row['referralCredit'], 2) }}
                    </div>
                </div>
                <div>
                    @if ($row['achievementPercent'] !== null)
                        <div class="sfp-progress">
                            <div class="sfp-progress-fill {{ $row['achievementPercent'] >= 100 ? 'sfp-progress-fill-complete' : '' }}" style="width:{{ min(100, $row['achievementPercent'] / $scalePercent * 100) }}%"></div>
                            @foreach ($slabs as $slab)
                                <span class="sfp-progress-marker" style="left:{{ $slab->min_achievement_percent / $scalePercent * 100 }}%"></span>
                            @endforeach
                        </div>
                        <div class="sfp-mono" style="font-size:11.5px;color:#66736F;margin-top:4px">{{ number_format((float) $row['achievementPercent'], 2) }}%</div>
                    @else
                        <span style="color:#94A19D">&mdash;</span>
                    @endif
                </div>
                <span class="sfp-mono">
                    @if ($row['slab'])
                        {{ number_format($row['slab']->incentive_percent, 2) }}%
                    @else
                        &mdash;
                    @endif
                </span>
                <span class="sfp-mono" style="font-size:12.5px">
                    @if ($row['amountToNextSlab'] !== null)
                        &#8377;{{ number_format((float) $row['amountToNextSlab'], 2) }}
                        <span style="color:#94A19D">for {{ number_format($row['nextSlab']->incentive_percent, 0) }}%</span>
                    @else
                        &mdash;
                    @endif
                </span>
                <span class="sfp-mono">&#8377;{{ number_format((float) $row['incentive'], 2) }}</span>
                <span class="sfp-mono">&#8377;{{ number_format((float) $row['bonus'], 2) }}</span>
                <span class="sfp-mono" style="font-weight:600">&#8377;{{ number_format((float) $row['totalEarned'], 2) }}</span>
            </div>
        @empty
            <div class="sfp-table-row">
                <span style="color:#66736F">No staff to show.</span>
            </div>
        @endforelse
    </div>
@endsection
