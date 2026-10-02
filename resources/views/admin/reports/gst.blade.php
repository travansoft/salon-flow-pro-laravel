@extends('layouts.admin')

@section('title', 'GST report')

@section('content')
    <div class="sfp-page-header">
        <div>
            <h1 class="sfp-page-title">GST report</h1>
            <p class="sfp-page-subtitle">Tax collected on invoices in {{ $month->format('F Y') }}.</p>
        </div>
        <form method="GET" action="{{ $tenantUrl->route('reports.gst') }}" style="display:flex;gap:8px;align-items:center">
            <input type="month" name="month" class="sfp-input" value="{{ $month->format('Y-m') }}">
            <button type="submit" class="sfp-btn-primary">Show</button>
            @can('dashboard.view')
                <a href="{{ $tenantUrl->route('reports.gstExport') }}?{{ http_build_query(['month' => $month->format('Y-m')]) }}" class="sfp-btn-outline"><i class="bi bi-file-earmark-excel"></i> Export to Excel</a>
            @endcan
        </form>
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:12px;margin-bottom:14px">
        @foreach (['taxable' => 'Taxable value', 'cgst' => 'CGST', 'sgst' => 'SGST', 'igst' => 'IGST', 'tax' => 'Total tax'] as $key => $label)
            <div class="sfp-card">
                <div class="sfp-label" style="margin-bottom:12px">{{ $label }}</div>
                <div class="sfp-heading" style="font-size:26px;line-height:1">&#8377;{{ number_format((float) $totals[$key], 2) }}</div>
            </div>
        @endforeach
    </div>

    <div class="sfp-card" style="padding:0;overflow:hidden;margin-bottom:14px">
        <div style="display:grid;grid-template-columns:repeat(5,1fr);padding:14px 20px;background:#F8FAF9;border-bottom:1px solid #E3EAE8;font-size:11.5px;letter-spacing:.06em;text-transform:uppercase;color:#94A19D">
            <span>GST rate</span><span style="text-align:right">Taxable value</span><span style="text-align:right">CGST</span><span style="text-align:right">SGST</span><span style="text-align:right">IGST</span>
        </div>
        @forelse ($rateSummary as $rate => $amounts)
            <div style="display:grid;grid-template-columns:repeat(5,1fr);padding:13px 20px;border-bottom:1px solid #EDF1F0;font-size:13.5px">
                <span>{{ (float) $rate }}%</span>
                <span class="sfp-mono" style="text-align:right">&#8377;{{ number_format((float) $amounts['taxable'], 2) }}</span>
                <span class="sfp-mono" style="text-align:right">&#8377;{{ number_format((float) $amounts['cgst'], 2) }}</span>
                <span class="sfp-mono" style="text-align:right">&#8377;{{ number_format((float) $amounts['sgst'], 2) }}</span>
                <span class="sfp-mono" style="text-align:right">&#8377;{{ number_format((float) $amounts['igst'], 2) }}</span>
            </div>
        @empty
            <div style="padding:20px;color:#94A19D;font-size:13.5px">No invoices in this month.</div>
        @endforelse
    </div>

    <div class="sfp-card" style="padding:0;overflow:hidden">
        <div style="display:grid;grid-template-columns:1.3fr .8fr 1.3fr 1.3fr repeat(5,.9fr);padding:14px 20px;background:#F8FAF9;border-bottom:1px solid #E3EAE8;font-size:11.5px;letter-spacing:.06em;text-transform:uppercase;color:#94A19D">
            <span>Invoice</span><span>Date</span><span>Client</span><span>GSTIN</span>
            <span style="text-align:right">Taxable</span><span style="text-align:right">CGST</span><span style="text-align:right">SGST</span><span style="text-align:right">IGST</span><span style="text-align:right">Total</span>
        </div>
        @forelse ($invoices as $invoice)
            <div style="display:grid;grid-template-columns:1.3fr .8fr 1.3fr 1.3fr repeat(5,.9fr);padding:13px 20px;border-bottom:1px solid #EDF1F0;align-items:center;font-size:13.5px">
                <span class="sfp-mono" style="font-size:12.5px"><a href="{{ $tenantUrl->route('bills.show', ['bill' => $invoice['bill_id']]) }}" class="sfp-action-link">{{ $invoice['invoice_number'] }}</a></span>
                <span style="color:#66736F">{{ $invoice['date']->format('d M Y') }}</span>
                <span>{{ $invoice['client'] }}</span>
                <span class="sfp-mono" style="font-size:12.5px">{{ $invoice['gstin'] ?? '-' }}</span>
                <span class="sfp-mono" style="text-align:right">{{ number_format((float) $invoice['taxable'], 2) }}</span>
                <span class="sfp-mono" style="text-align:right">{{ number_format((float) $invoice['cgst'], 2) }}</span>
                <span class="sfp-mono" style="text-align:right">{{ number_format((float) $invoice['sgst'], 2) }}</span>
                <span class="sfp-mono" style="text-align:right">{{ number_format((float) $invoice['igst'], 2) }}</span>
                <span class="sfp-mono" style="text-align:right">{{ number_format((float) $invoice['total'], 2) }}</span>
            </div>
        @empty
            <div style="padding:20px;color:#94A19D;font-size:13.5px">No invoices in this month.</div>
        @endforelse
    </div>
@endsection
