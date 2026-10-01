@extends('layouts.admin')

@section('title', 'Sales summary')

@section('content')
    <div class="sfp-page-header">
        <div>
            <h1 class="sfp-page-title">Sales summary</h1>
            <p class="sfp-page-subtitle">{{ $from->format('d M Y') }} &ndash; {{ $to->format('d M Y') }}. Figures update as bills are settled.</p>
        </div>
        <form id="rangeForm" method="GET" action="{{ $tenantUrl->route('reports.index') }}" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
            <button type="button" class="sfp-btn-outline" data-preset="today">Today</button>
            <button type="button" class="sfp-btn-outline" data-preset="week">This week</button>
            <button type="button" class="sfp-btn-outline" data-preset="month">This month</button>
            <input type="date" name="from" id="rangeFrom" class="sfp-input" style="width:auto" value="{{ $from->format('Y-m-d') }}">
            <span style="color:#94A19D">to</span>
            <input type="date" name="to" id="rangeTo" class="sfp-input" style="width:auto" value="{{ $to->format('Y-m-d') }}">
            <button type="submit" class="sfp-btn-primary">Apply</button>
            @can('reports.consolidated.view')
                <a href="{{ $tenantUrl->route('reports.consolidated') }}" class="sfp-btn-outline">All branches</a>
            @endcan
        </form>
    </div>

    @error('to')
        <div class="sfp-alert-error">{{ $message }}</div>
    @enderror

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(178px,1fr));gap:12px;margin-bottom:14px">
        <div class="sfp-card">
            <div class="sfp-label" style="margin-bottom:12px">Revenue</div>
            <div class="sfp-heading" style="font-size:32px;line-height:1">&#8377;{{ number_format((float) $totalRevenue, 2) }}</div>
            @if($revenueChange)
                <div style="margin-top:8px;font-size:12.5px;color:{{ $revenueChange['direction'] === 'up' ? '#2F6849' : '#A8506B' }}">
                    {{ $revenueChange['direction'] === 'up' ? '▲' : '▼' }} {{ $revenueChange['percent'] }}% vs previous period
                </div>
            @endif
        </div>
        <div class="sfp-card">
            <div class="sfp-label" style="margin-bottom:12px">Bills settled</div>
            <div class="sfp-heading" style="font-size:32px;line-height:1">{{ $billCount }}</div>
            <div style="margin-top:8px;font-size:12.5px;color:#66736F">Avg &#8377;{{ number_format((float) $avgBillValue, 2) }} per bill</div>
        </div>
        <div class="sfp-card">
            <div class="sfp-label" style="margin-bottom:12px">Clients served</div>
            <div class="sfp-heading" style="font-size:32px;line-height:1">{{ $uniqueClients }}</div>
        </div>
        <div class="sfp-card">
            <div class="sfp-label" style="margin-bottom:12px">Expenses</div>
            <div class="sfp-heading" style="font-size:32px;line-height:1">&#8377;{{ number_format((float) $expenseTotal, 2) }}</div>
        </div>
        <div class="sfp-card">
            <div class="sfp-label" style="margin-bottom:12px">Net (after refunds &amp; expenses)</div>
            <div class="sfp-heading" style="font-size:32px;line-height:1;color:{{ (float) $netAmount < 0 ? '#A8506B' : '#2F6849' }}">&#8377;{{ number_format((float) $netAmount, 2) }}</div>
        </div>
        <div class="sfp-card">
            <div class="sfp-label" style="margin-bottom:12px">GST collected</div>
            <div class="sfp-heading" style="font-size:22px;line-height:1">&#8377;{{ number_format((float) $gstCollected, 2) }}</div>
            <div style="margin-top:8px;font-size:12.5px;color:#66736F">Discounts &#8377;{{ number_format((float) $discountsGiven, 2) }} &middot; Refunds &#8377;{{ number_format((float) $refundTotal, 2) }}</div>
        </div>
        <div class="sfp-card">
            <div class="sfp-label" style="margin-bottom:12px">Low stock</div>
            <div class="sfp-heading" style="font-size:32px;line-height:1;color:{{ $lowStockCount > 0 ? '#A8506B' : '#2F6849' }}">{{ $lowStockCount }}</div>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px">
        <div class="sfp-card">
            <div style="margin-bottom:22px;font-size:15px;font-weight:500">
                Daily revenue
                @if($trendTruncated)
                    <span style="color:#94A19D;font-weight:400;font-size:12px">(last 31 days of range)</span>
                @endif
            </div>
            @php $peak = max(1, ...array_map(fn ($r) => (float) $r['amount'], $dailyRevenue)); @endphp
            <div style="display:flex;align-items:flex-end;gap:6px;height:200px">
                @foreach ($dailyRevenue as $point)
                    <div style="flex:1;min-width:0;display:flex;flex-direction:column;align-items:center;gap:6px;height:100%;justify-content:flex-end">
                        <span class="sfp-mono" style="font-size:10px;color:#16201D">{{ $point['short'] }}</span>
                        <div title="&#8377;{{ number_format((float) $point['amount'], 2) }}" style="width:100%;background:#1B4B8F;border-radius:6px 6px 0 0;height:{{ max(2, ((float) $point['amount'] / $peak) * 78) }}%"></div>
                        <span class="sfp-mono" style="font-size:9.5px;color:#AEBAB7;white-space:nowrap">{{ count($dailyRevenue) > 15 ? substr($point['label'], 0, 2) : $point['label'] }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="sfp-card">
            <div style="margin-bottom:22px;font-size:15px;font-weight:500">
                Footfall trend <span style="color:#94A19D;font-weight:400;font-size:12px">(clients per day)</span>
            </div>
            @php $footfallPeak = max(1, ...array_map(fn ($r) => $r['footfall'], $dailyRevenue)); @endphp
            <div style="display:flex;align-items:flex-end;gap:6px;height:200px">
                @foreach ($dailyRevenue as $point)
                    <div style="flex:1;min-width:0;display:flex;flex-direction:column;align-items:center;gap:6px;height:100%;justify-content:flex-end">
                        <span class="sfp-mono" style="font-size:10px;color:#16201D">{{ $point['footfall'] }}</span>
                        <div style="width:100%;background:#2E5F4C;border-radius:6px 6px 0 0;height:{{ max(2, ($point['footfall'] / $footfallPeak) * 78) }}%"></div>
                        <span class="sfp-mono" style="font-size:9.5px;color:#AEBAB7;white-space:nowrap">{{ count($dailyRevenue) > 15 ? substr($point['label'], 0, 2) : $point['label'] }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px">
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

        <div class="sfp-card">
            <div style="font-size:15px;font-weight:500;margin-bottom:18px">Expenses by category</div>
            @if (count($expensesByCategory) === 0)
                <p style="color:#94A19D;font-size:13.5px">No expenses in this period.</p>
            @else
                @php $expPeak = max(1, ...array_map(fn ($e) => (float) $e['amount'], $expensesByCategory)); @endphp
                <div style="display:grid;gap:14px">
                    @foreach ($expensesByCategory as $category)
                        <div>
                            <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:6px">
                                <span>{{ $category['name'] }}</span>
                                <span class="sfp-mono" style="color:#66736F">&#8377;{{ number_format((float) $category['amount'], 2) }}</span>
                            </div>
                            <div style="height:7px;background:#ECF0EF;border-radius:999px;overflow:hidden">
                                <div style="height:100%;background:#A8506B;width:{{ ((float) $category['amount'] / $expPeak) * 100 }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
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

@section('scripts')
    <script>
        (function () {
            var form = document.getElementById('rangeForm');
            var pad = function (n) { return String(n).padStart(2, '0'); };
            var fmt = function (d) { return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()); };

            form.querySelectorAll('[data-preset]').forEach(function (button) {
                button.addEventListener('click', function () {
                    var today = new Date();
                    var start = new Date(today);

                    if (button.dataset.preset === 'week') {
                        start.setDate(today.getDate() - ((today.getDay() + 6) % 7));
                    } else if (button.dataset.preset === 'month') {
                        start = new Date(today.getFullYear(), today.getMonth(), 1);
                    }

                    document.getElementById('rangeFrom').value = fmt(start);
                    document.getElementById('rangeTo').value = fmt(today);
                    form.submit();
                });
            });
        })();
    </script>
@endsection
