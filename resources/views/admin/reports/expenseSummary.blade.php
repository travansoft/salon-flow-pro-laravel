@extends('layouts.admin')

@section('title', 'Expense summary')

@section('content')
    <div class="sfp-page-header">
        <div>
            <h1 class="sfp-page-title">Expense summary</h1>
            <p class="sfp-page-subtitle">Category-wise expenses for the selected dates. Select a category to see its expenses.</p>
        </div>
        <form method="GET" action="{{ $tenantUrl->route('reports.expenseSummary') }}" style="display:flex;gap:8px;align-items:center">
            <input type="date" name="from" class="sfp-input" value="{{ $from->format('Y-m-d') }}">
            <span style="color:#94A19D">to</span>
            <input type="date" name="to" class="sfp-input" value="{{ $to->format('Y-m-d') }}">
            <button type="submit" class="sfp-btn-primary">Show</button>
        </form>
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:12px;margin-bottom:14px">
        <div class="sfp-card">
            <div class="sfp-label" style="margin-bottom:12px">Total expenses</div>
            <div class="sfp-heading" style="font-size:32px;line-height:1">&#8377;{{ number_format((float) $total, 2) }}</div>
            <div style="margin-top:12px;font-size:12.5px;color:#66736F">{{ $count }} expenses across {{ $categories->count() }} categories</div>
        </div>
    </div>

    <div class="sfp-card" style="padding:0;overflow:hidden">
        <div style="display:grid;grid-template-columns:2fr .7fr 1fr .9fr;padding:14px 20px;background:#F8FAF9;border-bottom:1px solid #E3EAE8;font-size:11.5px;letter-spacing:.06em;text-transform:uppercase;color:#94A19D">
            <span>Category</span><span style="text-align:right">Expenses</span><span style="text-align:right">Total</span><span style="text-align:right">Share</span>
        </div>
        @forelse ($categories as $row)
            <a href="{{ $tenantUrl->route('expenses.index') }}?{{ http_build_query(['from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d'), 'category_id' => $row['category_id'] ?? 'none']) }}" style="display:grid;grid-template-columns:2fr .7fr 1fr .9fr;padding:13px 20px;border-bottom:1px solid #EDF1F0;align-items:center;font-size:13.5px;text-decoration:none;color:inherit">
                <span class="sfp-action-link">{{ $row['name'] }}</span>
                <span class="sfp-mono" style="text-align:right;color:#66736F">{{ $row['count'] }}</span>
                <span class="sfp-mono" style="text-align:right">&#8377;{{ number_format((float) $row['total'], 2) }}</span>
                <span class="sfp-mono" style="text-align:right;color:#66736F">{{ number_format($row['share'], 1) }}%</span>
            </a>
        @empty
            <div style="padding:20px;color:#94A19D;font-size:13.5px">No expenses in this period.</div>
        @endforelse
        @if($categories->isNotEmpty())
            <div style="display:grid;grid-template-columns:2fr .7fr 1fr .9fr;padding:14px 20px;background:#F8FAF9;font-size:13.5px;font-weight:600">
                <span>Total</span>
                <span class="sfp-mono" style="text-align:right">{{ $count }}</span>
                <span class="sfp-mono" style="text-align:right">&#8377;{{ number_format((float) $total, 2) }}</span>
                <span></span>
            </div>
        @endif
    </div>
@endsection
