@extends('layouts.admin')

@section('title', 'Consolidated Reports')

@section('content')
    <div class="sfp-page-header">
        <div>
            <h1 class="sfp-page-title">Consolidated reports</h1>
            <p class="sfp-page-subtitle">Combined figures across all branches. Figures update as bills are settled.</p>
        </div>
        <div style="display:flex;gap:8px">
            @foreach (['today' => 'Today', 'week' => 'This week', 'month' => 'This month'] as $value => $label)
                <a href="{{ $tenantUrl->route('reports.consolidated').'?period='.$value }}"
                   class="{{ $period === $value ? 'sfp-btn-primary' : 'sfp-btn-outline' }}">{{ $label }}</a>
            @endforeach
        </div>
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(178px,1fr));gap:12px;margin-bottom:14px">
        <div class="sfp-card">
            <div class="sfp-label" style="margin-bottom:12px">Revenue (all branches)</div>
            <div class="sfp-heading" style="font-size:32px;line-height:1">&#8377;{{ number_format((float) $totalRevenue, 2) }}</div>
        </div>
        <div class="sfp-card">
            <div class="sfp-label" style="margin-bottom:12px">Bills settled</div>
            <div class="sfp-heading" style="font-size:32px;line-height:1">{{ $billCount }}</div>
        </div>
        <div class="sfp-card">
            <div class="sfp-label" style="margin-bottom:12px">Low stock</div>
            <div class="sfp-heading" style="font-size:32px;line-height:1;color:{{ $lowStockCount > 0 ? '#A8506B' : '#2F6849' }}">{{ $lowStockCount }}</div>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px">
        <div class="sfp-card">
            <div style="display:flex;align-items:baseline;justify-content:space-between;margin-bottom:22px">
                <span style="font-size:15px;font-weight:500">Revenue, last 10 days</span>
            </div>
            <div style="display:flex;align-items:flex-end;gap:10px;height:184px">
                @php
                    $peak = max(1, ...array_map(fn ($r) => (float) $r['amount'], $revenueTrend));
                @endphp
                @foreach ($revenueTrend as $point)
                    <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:8px;height:100%;justify-content:flex-end">
                        <div style="width:100%;background:#1B4B8F;border-radius:6px 6px 0 0;height:{{ max(4, ((float) $point['amount'] / $peak) * 100) }}%"></div>
                        <span class="sfp-mono" style="font-size:10.5px;color:#AEBAB7">{{ $point['label'] }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="sfp-card">
            <div style="font-size:15px;font-weight:500;margin-bottom:18px">Top services by revenue</div>
            @if (count($topServices) === 0)
                <p style="color:#94A19D;font-size:13.5px">No paid bills in this period yet.</p>
            @else
                <div style="display:grid;gap:14px;margin-bottom:22px">
                    @php $topPeak = max(1, ...array_map(fn ($s) => (float) $s['amount'], $topServices)); @endphp
                    @foreach ($topServices as $service)
                        <div>
                            <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:6px">
                                <span>{{ $service['name'] }}</span>
                                <span class="sfp-mono" style="color:#66736F">&#8377;{{ number_format((float) $service['amount'], 2) }}</span>
                            </div>
                            <div style="height:7px;background:#ECF0EF;border-radius:999px;overflow:hidden">
                                <div style="height:100%;background:#1B4B8F;width:{{ ((float) $service['amount'] / $topPeak) * 100 }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            <div style="padding-top:18px;border-top:1px solid #E3EAE8;display:grid;gap:14px">
                @if (count($paymentMix) === 0)
                    <p style="color:#94A19D;font-size:13.5px">No payments recorded in this period yet.</p>
                @else
                    @php $mixPeak = max(1, ...array_map(fn ($m) => (float) $m['amount'], $paymentMix)); @endphp
                    @foreach ($paymentMix as $mix)
                        <div>
                            <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:6px">
                                <span style="text-transform:capitalize">{{ $mix['method'] }}</span>
                                <span class="sfp-mono" style="color:#66736F">&#8377;{{ number_format((float) $mix['amount'], 2) }}</span>
                            </div>
                            <div style="height:7px;background:#ECF0EF;border-radius:999px;overflow:hidden">
                                <div style="height:100%;background:#2E5F4C;width:{{ ((float) $mix['amount'] / $mixPeak) * 100 }}%"></div>
                            </div>
                        </div>
                    @endforeach
                @endif
            </div>
        </div>
    </div>

    <div class="sfp-card" style="padding:0;overflow:hidden">
        <div style="display:grid;grid-template-columns:1.6fr .8fr 1fr;padding:14px 20px;background:#F8FAF9;border-bottom:1px solid #E3EAE8;font-size:11.5px;letter-spacing:.06em;text-transform:uppercase;color:#94A19D">
            <span>Staff</span><span style="text-align:right">Services</span><span style="text-align:right">Revenue</span>
        </div>
        @forelse ($staffPerformance as $row)
            <div style="display:grid;grid-template-columns:1.6fr .8fr 1fr;padding:15px 20px;border-bottom:1px solid #EDF1F0;align-items:center">
                <span style="font-size:14px">{{ $row['name'] }}</span>
                <span class="sfp-mono" style="text-align:right;font-size:13px">{{ $row['services'] }}</span>
                <span class="sfp-mono" style="text-align:right;font-size:13.5px">&#8377;{{ number_format((float) $row['revenue'], 2) }}</span>
            </div>
        @empty
            <div style="padding:20px;color:#94A19D;font-size:13.5px">No staff activity in this period yet.</div>
        @endforelse
    </div>
@endsection
