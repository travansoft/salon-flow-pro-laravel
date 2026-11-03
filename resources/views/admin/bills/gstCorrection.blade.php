@extends('layouts.admin')

@section('title', 'Correct GST rate')

@section('content')
    <div class="sfp-page-header">
        <div>
            <h1 class="sfp-page-title">Correct GST rate</h1>
            <p class="sfp-page-subtitle">Re-state GST-inclusive bills in a date range at a different rate. The amount each customer paid does not change; only the taxable value and GST inside it do.</p>
        </div>
        <div class="sfp-row">
            <a href="{{ $tenantUrl->route('bills.index') }}" class="sfp-btn-outline">Back to billing</a>
        </div>
    </div>

    @if (session('status'))
        <div class="sfp-alert sfp-alert-success" style="margin-bottom:16px">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ $tenantUrl->route('billGstCorrections.store') }}" class="sfp-card" style="margin-bottom:16px">
        @csrf

        <div class="sfp-split-2">
            <div class="sfp-field">
                <label class="sfp-label" for="gst-from-date">Bills from</label>
                <input type="date" id="gst-from-date" name="from_date" class="sfp-input" value="{{ old('from_date', $input['from_date'] ?? '') }}">
                @error('from_date')
                    <span class="sfp-invalid-feedback">{{ $message }}</span>
                @enderror
            </div>

            <div class="sfp-field">
                <label class="sfp-label" for="gst-to-date">Bills to</label>
                <input type="date" id="gst-to-date" name="to_date" class="sfp-input" value="{{ old('to_date', $input['to_date'] ?? '') }}">
                @error('to_date')
                    <span class="sfp-invalid-feedback">{{ $message }}</span>
                @enderror
            </div>
        </div>

        <div class="sfp-split-2">
            <div class="sfp-field">
                <label class="sfp-label" for="gst-from-rate">Rate the bills were made at (%)</label>
                <input type="number" step="0.01" min="0" max="100" id="gst-from-rate" name="from_rate" class="sfp-input" value="{{ old('from_rate', $input['from_rate'] ?? '') }}" placeholder="e.g. 18">
                @error('from_rate')
                    <span class="sfp-invalid-feedback">{{ $message }}</span>
                @enderror
            </div>

            <div class="sfp-field">
                <label class="sfp-label" for="gst-to-rate">Correct rate (%)</label>
                <input type="number" step="0.01" min="0" max="100" id="gst-to-rate" name="to_rate" class="sfp-input" value="{{ old('to_rate', $input['to_rate'] ?? '') }}" placeholder="e.g. 5">
                @error('to_rate')
                    <span class="sfp-invalid-feedback">{{ $message }}</span>
                @enderror
            </div>
        </div>

        <p style="font-size:12.5px;color:#66736F;margin:0 0 14px">Only bills at the first rate are touched. Cancelled bills and bills made with GST added on top are skipped.</p>

        <div class="sfp-form-actions">
            <button type="submit" class="sfp-btn-primary">Preview changes</button>
        </div>
    </form>

    @if ($result)
        <div class="sfp-card">
            <h2 class="sfp-card-title">Preview</h2>

            @if ($result['bills'] === 0)
                <p style="font-size:14px;color:#66736F;margin:0">No bills in that range were made at {{ $input['from_rate'] }}%.</p>
            @else
                <p style="font-size:14px;margin:0 0 14px">
                    <strong>{{ $result['bills'] }}</strong> bill(s), <strong>{{ $result['lines'] }}</strong> line(s) will change.
                    Total GST goes from <span class="sfp-mono">&#8377;{{ number_format((float) $result['taxBefore'], 2) }}</span>
                    to <span class="sfp-mono">&#8377;{{ number_format((float) $result['taxAfter'], 2) }}</span>.
                    Customer totals stay the same.
                </p>

                <div class="sfp-table-wrap" style="margin-bottom:14px">
                    <div class="sfp-table-head-row" style="grid-template-columns:1.4fr 1fr 1fr 1fr 1fr">
                        <span>Invoice</span>
                        <span>Date</span>
                        <span style="text-align:right">GST now</span>
                        <span style="text-align:right">GST after</span>
                        <span style="text-align:right">Total</span>
                    </div>
                    @foreach ($result['preview'] as $row)
                        <div class="sfp-table-row" style="grid-template-columns:1.4fr 1fr 1fr 1fr 1fr">
                            <span style="font-size:13.5px">{{ $row['invoice'] }}</span>
                            <span style="font-size:13.5px">{{ $row['date'] }}</span>
                            <span class="sfp-mono" style="text-align:right">&#8377;{{ number_format((float) $row['taxBefore'], 2) }}</span>
                            <span class="sfp-mono" style="text-align:right">&#8377;{{ number_format((float) $row['taxAfter'], 2) }}</span>
                            <span class="sfp-mono" style="text-align:right">&#8377;{{ number_format((float) $row['total'], 2) }}</span>
                        </div>
                    @endforeach
                </div>

                @if ($result['bills'] > count($result['preview']))
                    <p style="font-size:12.5px;color:#66736F;margin:0 0 14px">Showing the first {{ count($result['preview']) }} of {{ $result['bills'] }} bills.</p>
                @endif
            @endif

            @if ($result['skipped'] !== [])
                <div class="sfp-alert" style="margin-bottom:14px">
                    <strong>{{ count($result['skipped']) }} bill(s) will be skipped:</strong>
                    <ul style="margin:6px 0 0;padding-left:18px">
                        @foreach (array_slice($result['skipped'], 0, 10) as $reason)
                            <li style="font-size:13px">{{ $reason }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if ($result['bills'] > 0)
                <form method="POST" action="{{ $tenantUrl->route('billGstCorrections.store') }}" onsubmit="return confirm('Change GST on {{ $result['bills'] }} bill(s)? This updates saved bills and is recorded on each bill\'s history.')">
                    @csrf
                    <input type="hidden" name="from_date" value="{{ $input['from_date'] }}">
                    <input type="hidden" name="to_date" value="{{ $input['to_date'] }}">
                    <input type="hidden" name="from_rate" value="{{ $input['from_rate'] }}">
                    <input type="hidden" name="to_rate" value="{{ $input['to_rate'] }}">
                    <input type="hidden" name="confirm" value="1">

                    <div class="sfp-form-actions">
                        <button type="submit" class="sfp-btn-primary">Apply to {{ $result['bills'] }} bill(s)</button>
                    </div>
                </form>
            @endif
        </div>
    @endif
@endsection
