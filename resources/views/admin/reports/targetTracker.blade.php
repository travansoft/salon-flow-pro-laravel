@extends('layouts.admin')

@section('title', 'Target tracker')

@section('content')
    <div class="sfp-page-header">
        <div>
            <h1 class="sfp-page-title">Target tracker</h1>
            <p class="sfp-page-subtitle">
                Expected versus actual progress for {{ $month->format('F Y') }}. Expected is the monthly target spread evenly over the days.
                Actual comes from paid bills, including GST, net of discount and refunds.
            </p>
        </div>
        <form method="GET" action="{{ $tenantUrl->route('reports.targetTracker') }}" style="display:flex;gap:8px;align-items:center">
            <input type="month" name="month" class="sfp-input" value="{{ $month->format('Y-m') }}">
            @if($selectedStaff)
                <input type="hidden" name="staff" value="{{ $selectedStaff['staff']->id }}">
            @endif
            <button type="submit" class="sfp-btn-primary">Show</button>
            @can('dashboard.view')
                <a href="{{ $tenantUrl->route('reports.targetTrackerExport') }}?{{ http_build_query($monthQuery) }}" class="sfp-btn-outline"><i class="bi bi-file-earmark-excel"></i> Export to Excel</a>
            @endcan
        </form>
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:12px;margin-bottom:14px">
        <div class="sfp-card">
            <div class="sfp-label" style="margin-bottom:12px">{{ $selectedStaff ? $selectedStaff['staff']->name : 'Salon' }} target</div>
            <div class="sfp-heading" style="font-size:26px;line-height:1">&#8377;{{ number_format((float) $detail['target'], 2) }}</div>
        </div>
        <div class="sfp-card">
            <div class="sfp-label" style="margin-bottom:12px">Expected till now</div>
            <div class="sfp-heading" style="font-size:26px;line-height:1">&#8377;{{ number_format((float) $detail['expectedToDate'], 2) }}</div>
        </div>
        <div class="sfp-card">
            <div class="sfp-label" style="margin-bottom:12px">Actual till now</div>
            <div class="sfp-heading" style="font-size:26px;line-height:1">&#8377;{{ number_format((float) $detail['actual'], 2) }}</div>
        </div>
        <div class="sfp-card">
            <div class="sfp-label" style="margin-bottom:12px">Variance</div>
            <div class="sfp-heading" style="font-size:26px;line-height:1;color:{{ bccomp($detail['variance'], '0', 2) >= 0 ? '#1E7B4F' : '#C0392B' }}">
                {{ bccomp($detail['variance'], '0', 2) >= 0 ? '+' : '-' }}&#8377;{{ number_format(abs((float) $detail['variance']), 2) }}
            </div>
        </div>
        <div class="sfp-card">
            <div class="sfp-label" style="margin-bottom:12px">Achieved of target</div>
            <div class="sfp-heading" style="font-size:26px;line-height:1">{{ $detail['achievementPercent'] !== null ? number_format((float) $detail['achievementPercent'], 1).'%' : '—' }}</div>
        </div>
        <div class="sfp-card">
            <div class="sfp-label" style="margin-bottom:12px">Projected month-end</div>
            <div class="sfp-heading" style="font-size:26px;line-height:1">{{ $detail['projected'] !== null ? '₹'.number_format((float) $detail['projected'], 2) : '—' }}</div>
        </div>
    </div>

    <div class="sfp-card" style="padding:0;overflow:hidden;margin-bottom:14px">
        <div style="padding:14px 20px;font-weight:600;border-bottom:1px solid #E3EAE8">Staff summary</div>
        <div style="display:grid;grid-template-columns:1.2fr repeat(4,1fr) 1.2fr .8fr;padding:14px 20px;background:#F8FAF9;border-bottom:1px solid #E3EAE8;font-size:11.5px;letter-spacing:.06em;text-transform:uppercase;color:#94A19D">
            <span>Staff</span><span style="text-align:right">Target</span><span style="text-align:right">Expected</span><span style="text-align:right">Actual</span><span style="text-align:right">Variance</span><span>Progress</span><span>Status</span>
        </div>
        <div style="display:grid;grid-template-columns:1.2fr repeat(4,1fr) 1.2fr .8fr;padding:13px 20px;border-bottom:1px solid #EDF1F0;align-items:center;font-size:13.5px;font-weight:600">
            <a href="{{ $tenantUrl->route('reports.targetTracker') }}?{{ http_build_query($monthQuery) }}" class="sfp-action-link">Salon (all staff)</a>
            <span class="sfp-mono" style="text-align:right">{{ number_format((float) $salon['target'], 2) }}</span>
            <span class="sfp-mono" style="text-align:right">{{ number_format((float) $salon['expectedToDate'], 2) }}</span>
            <span class="sfp-mono" style="text-align:right">{{ number_format((float) $salon['actual'], 2) }}</span>
            <span class="sfp-mono" style="text-align:right">{{ number_format((float) $salon['variance'], 2) }}</span>
            <div>
                <div class="sfp-progress"><div class="sfp-progress-fill {{ (float) $salon['achievementPercent'] >= 100 ? 'sfp-progress-fill-complete' : '' }}" style="width:{{ min(100, (float) $salon['achievementPercent']) }}%"></div></div>
                <div class="sfp-mono" style="font-size:11.5px;color:#66736F;margin-top:4px">{{ $salon['achievementPercent'] !== null ? number_format((float) $salon['achievementPercent'], 1).'%' : '—' }}</div>
            </div>
            <span style="color:{{ $statusStyles[$salon['status'] ?? 'no-target']['color'] }}">{{ $salon['status'] ? $statusStyles[$salon['status']]['label'] : '—' }}</span>
        </div>
        @forelse ($staffRows as $row)
            <div style="display:grid;grid-template-columns:1.2fr repeat(4,1fr) 1.2fr .8fr;padding:13px 20px;border-bottom:1px solid #EDF1F0;align-items:center;font-size:13.5px;{{ $selectedStaff && $selectedStaff['staff']->id === $row['staff']->id ? 'background:#F3F8F6' : '' }}">
                <a href="{{ $tenantUrl->route('reports.targetTracker') }}?{{ http_build_query([...$monthQuery, 'staff' => $row['staff']->id]) }}" class="sfp-action-link">{{ $row['staff']->name }}</a>
                <span class="sfp-mono" style="text-align:right">{{ $row['hasTarget'] ? number_format((float) $row['target'], 2) : '—' }}</span>
                <span class="sfp-mono" style="text-align:right">{{ number_format((float) $row['expectedToDate'], 2) }}</span>
                <span class="sfp-mono" style="text-align:right">{{ number_format((float) $row['actual'], 2) }}</span>
                <span class="sfp-mono" style="text-align:right;color:{{ bccomp($row['variance'], '0', 2) >= 0 ? '#1E7B4F' : '#C0392B' }}">{{ number_format((float) $row['variance'], 2) }}</span>
                <div>
                    @if($row['achievementPercent'] !== null)
                        <div class="sfp-progress"><div class="sfp-progress-fill {{ (float) $row['achievementPercent'] >= 100 ? 'sfp-progress-fill-complete' : '' }}" style="width:{{ min(100, (float) $row['achievementPercent']) }}%"></div></div>
                        <div class="sfp-mono" style="font-size:11.5px;color:#66736F;margin-top:4px">{{ number_format((float) $row['achievementPercent'], 1) }}%</div>
                    @else
                        <span style="color:#94A19D">—</span>
                    @endif
                </div>
                <span style="color:{{ $statusStyles[$row['status'] ?? 'no-target']['color'] }}">{{ $row['status'] ? $statusStyles[$row['status']]['label'] : '—' }}</span>
            </div>
        @empty
            <div style="padding:20px;color:#94A19D;font-size:13.5px">No active staff.</div>
        @endforelse
    </div>

    <div class="sfp-card" style="padding:0;overflow:hidden;margin-bottom:14px">
        <div style="padding:14px 20px;font-weight:600;border-bottom:1px solid #E3EAE8">Week-wise: {{ $selectedStaff ? $selectedStaff['staff']->name : 'Salon' }}</div>
        <div style="display:grid;grid-template-columns:1.4fr repeat(4,1fr) .8fr;padding:14px 20px;background:#F8FAF9;border-bottom:1px solid #E3EAE8;font-size:11.5px;letter-spacing:.06em;text-transform:uppercase;color:#94A19D">
            <span>Week</span><span style="text-align:right">Expected</span><span style="text-align:right">Actual</span><span style="text-align:right">Variance</span><span style="text-align:right">% of week</span><span>Status</span>
        </div>
        @foreach ($detail['weeks'] as $week)
            <div style="display:grid;grid-template-columns:1.4fr repeat(4,1fr) .8fr;padding:13px 20px;border-bottom:1px solid #EDF1F0;align-items:center;font-size:13.5px">
                <span>Week {{ $week['number'] }} <span style="color:#94A19D">({{ $week['from']->format('d M') }} - {{ $week['to']->format('d M') }})</span></span>
                <span class="sfp-mono" style="text-align:right">{{ number_format((float) $week['expected'], 2) }}</span>
                <span class="sfp-mono" style="text-align:right">{{ $week['actual'] !== null ? number_format((float) $week['actual'], 2) : '—' }}</span>
                <span class="sfp-mono" style="text-align:right;color:{{ $week['variance'] !== null && bccomp($week['variance'], '0', 2) < 0 ? '#C0392B' : '#1E7B4F' }}">{{ $week['variance'] !== null ? number_format((float) $week['variance'], 2) : '—' }}</span>
                <span class="sfp-mono" style="text-align:right">{{ $week['achievementPercent'] !== null ? number_format((float) $week['achievementPercent'], 1).'%' : '—' }}</span>
                <span style="color:{{ $statusStyles[$week['status'] ?? 'no-target']['color'] }}">{{ $week['status'] ? $statusStyles[$week['status']]['label'] : '—' }}</span>
            </div>
        @endforeach
    </div>

    <div class="sfp-card" style="padding:0;overflow:hidden">
        <div style="padding:14px 20px;font-weight:600;border-bottom:1px solid #E3EAE8">Day-wise: {{ $selectedStaff ? $selectedStaff['staff']->name : 'Salon' }}</div>
        <div style="display:grid;grid-template-columns:1fr repeat(6,1fr) .8fr;padding:14px 20px;background:#F8FAF9;border-bottom:1px solid #E3EAE8;font-size:11.5px;letter-spacing:.06em;text-transform:uppercase;color:#94A19D">
            <span>Date</span><span style="text-align:right">Expected</span><span style="text-align:right">Actual</span><span style="text-align:right">Cum. expected</span><span style="text-align:right">Cum. actual</span><span style="text-align:right">Variance</span><span></span><span>Status</span>
        </div>
        @foreach ($detail['days'] as $day)
            <div style="display:grid;grid-template-columns:1fr repeat(6,1fr) .8fr;padding:11px 20px;border-bottom:1px solid #EDF1F0;align-items:center;font-size:13.5px;{{ $day['isToday'] ? 'background:#F3F8F6' : '' }}">
                <span>{{ $day['date']->format('D, d M') }}</span>
                <span class="sfp-mono" style="text-align:right">{{ number_format((float) $day['expected'], 2) }}</span>
                <span class="sfp-mono" style="text-align:right">{{ $day['actual'] !== null ? number_format((float) $day['actual'], 2) : '—' }}</span>
                <span class="sfp-mono" style="text-align:right">{{ number_format((float) $day['cumulativeExpected'], 2) }}</span>
                <span class="sfp-mono" style="text-align:right">{{ $day['cumulativeActual'] !== null ? number_format((float) $day['cumulativeActual'], 2) : '—' }}</span>
                <span class="sfp-mono" style="text-align:right;color:{{ $day['variance'] !== null && bccomp($day['variance'], '0', 2) < 0 ? '#C0392B' : '#1E7B4F' }}">{{ $day['variance'] !== null ? number_format((float) $day['variance'], 2) : '—' }}</span>
                <span></span>
                <span style="color:{{ $statusStyles[$day['status'] ?? 'no-target']['color'] }}">{{ $day['status'] ? $statusStyles[$day['status']]['label'] : '—' }}</span>
            </div>
        @endforeach
    </div>
@endsection
