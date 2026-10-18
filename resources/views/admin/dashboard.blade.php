@extends('layouts.admin')

@section('title', 'Dashboard')

@section('content')
    <div class="sfp-page-header">
        <div>
            <h1 class="sfp-page-title">Good morning, {{ auth()->user()->name }}</h1>
            <p class="sfp-page-subtitle">Here's how {{ $tenant->name ?? 'your studio' }} is doing {{ $periodLabel }}.</p>
        </div>
        <form id="rangeForm" method="GET" action="{{ $tenantUrl->route('tenant.dashboard') }}" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
            <input type="hidden" name="period" id="rangePeriod" value="{{ $period }}">
            <button type="button" class="{{ $period === 'today' ? 'sfp-btn-primary' : 'sfp-btn-outline' }}" data-preset="today">Today</button>
            <button type="button" class="{{ $period === 'week' ? 'sfp-btn-primary' : 'sfp-btn-outline' }}" data-preset="week">This week</button>
            <button type="button" class="{{ $period === 'month' ? 'sfp-btn-primary' : 'sfp-btn-outline' }}" data-preset="month">This month</button>
            <input type="date" name="from" id="rangeFrom" class="sfp-input" style="width:auto" value="{{ $from->format('Y-m-d') }}">
            <span style="color:#94A19D">to</span>
            <input type="date" name="to" id="rangeTo" class="sfp-input" style="width:auto" value="{{ $to->format('Y-m-d') }}">
            <button type="submit" class="{{ $period === 'custom' ? 'sfp-btn-primary' : 'sfp-btn-outline' }}" data-custom>Apply</button>
        </form>
    </div>

    @if($errors->any())
        <div class="sfp-alert-error">{{ $errors->first() }}</div>
    @endif

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(178px,1fr));gap:12px">
        <div class="sfp-card">
            <div class="sfp-label" style="margin-bottom:12px">{{ $periodTitle }} Revenue</div>
            <div class="sfp-heading" style="font-size:34px;line-height:1">&#8377;{{ number_format((float) $revenue, 2) }}</div>
            @if($revenueChange)
                <div style="margin-top:8px;font-size:12.5px;color:{{ $revenueChange['direction'] === 'up' ? '#2F6849' : '#A8506B' }}">
                    {{ $revenueChange['direction'] === 'up' ? '▲' : '▼' }} {{ $revenueChange['percent'] }}% vs previous period
                </div>
            @endif
        </div>

        <div class="sfp-card">
            <div class="sfp-label" style="margin-bottom:12px">Customers (Footfall)</div>
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
                @can('inventory.view')
                    <a href="{{ $tenantUrl->route('lowStockReport.index') }}" style="display:inline-block;margin-top:10px;font-size:12.5px;color:#1B4B8F">View low stock report</a>
                @endcan
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

    @if(count($revenuePoints) > 1)
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:12px;margin-top:14px">
            <div class="sfp-card">
                <div style="margin-bottom:22px;font-size:15px;font-weight:500">
                    Revenue trend
                    @if($trendTruncated)
                        <span style="color:#94A19D;font-weight:400;font-size:12px">(last 31 days of range)</span>
                    @endif
                </div>
                <x-bar-chart :points="$revenuePoints" />
            </div>

            <div class="sfp-card">
                <div style="margin-bottom:22px;font-size:15px;font-weight:500">
                    Footfall trend <span style="color:#94A19D;font-weight:400;font-size:12px">(clients per day)</span>
                </div>
                <x-bar-chart :points="$footfallPoints" color="#2E5F4C" />
            </div>
        </div>
    @endif

    <div class="sfp-card" style="margin-top:14px">
        <div style="font-size:15px;font-weight:500;margin-bottom:18px">Payment mix</div>
        @if(count($paymentMix) === 0)
            <p style="color:#94A19D;font-size:13.5px;margin:0">No payments recorded in this period yet.</p>
        @else
            @php $mixPeak = max(1, ...array_map(fn ($mix) => (float) $mix['amount'], $paymentMix)); @endphp
            <div style="display:grid;gap:14px">
                @foreach($paymentMix as $mix)
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
            </div>
        @endif
    </div>

    <div style="margin-top:16px">
        @include('admin.bills.partials.drafts', ['drafts' => $drafts])
    </div>
@endsection

@section('scripts')
    <script>
        (function () {
            var form = document.getElementById('rangeForm');
            var period = document.getElementById('rangePeriod');
            var from = document.getElementById('rangeFrom');
            var to = document.getElementById('rangeTo');

            form.querySelectorAll('[data-preset]').forEach(function (button) {
                button.addEventListener('click', function () {
                    period.value = button.dataset.preset;
                    from.disabled = true;
                    to.disabled = true;
                    form.submit();
                });
            });

            form.addEventListener('submit', function () {
                if (!from.disabled) {
                    period.value = 'custom';
                }
            });
        })();
    </script>
@endsection
