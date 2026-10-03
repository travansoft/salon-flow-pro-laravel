@extends('layouts.admin')

@section('title', 'Expenses')

@section('content')
    <div class="sfp-page-header">
        <div>
            <h1 class="sfp-page-title">Expenses</h1>
            <p class="sfp-page-subtitle">{{ $from->format('d M Y') }} &ndash; {{ $to->format('d M Y') }} &middot; {{ $expenses->count() }} expenses: <span class="sfp-mono">&#8377;{{ number_format((float) $total, 2) }}</span></p>
        </div>
        <div class="sfp-row">
            @can('dashboard.view')
                <a href="{{ $tenantUrl->route('reports.expenseSummary') }}?{{ http_build_query(['from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d')]) }}" class="sfp-btn-outline">Category summary</a>
            @endcan
            @can('expenses.view')
                <a href="{{ $tenantUrl->route('expenseCategories.index') }}" class="sfp-btn-outline">Manage categories</a>
            @endcan
            @can('expenses.create')
                <a href="{{ $tenantUrl->route('expenses.create') }}" class="sfp-btn-pill-dark">+ Add expense</a>
            @endcan
        </div>
    </div>

    <form method="GET" action="{{ $tenantUrl->route('expenses.index') }}" class="sfp-row" style="margin-bottom:14px;flex-wrap:wrap;gap:8px;align-items:center">
        <input type="date" name="from" class="sfp-input" style="margin-bottom:0;max-width:170px" value="{{ $from->format('Y-m-d') }}">
        <span style="color:#94A19D">to</span>
        <input type="date" name="to" class="sfp-input" style="margin-bottom:0;max-width:170px" value="{{ $to->format('Y-m-d') }}">
        <select name="category_id" class="sfp-input" style="margin-bottom:0;max-width:200px">
            <option value="">All categories</option>
            <option value="none" @selected($filters['category_id'] === 'none')>Uncategorised</option>
            @foreach ($categories as $category)
                <option value="{{ $category->id }}" @selected((string) $filters['category_id'] === (string) $category->id)>{{ $category->name }}</option>
            @endforeach
        </select>
        <select name="payment_method" class="sfp-input" style="margin-bottom:0;max-width:160px">
            <option value="">All payment modes</option>
            @foreach (['cash' => 'Cash', 'upi' => 'UPI', 'card' => 'Card'] as $method => $label)
                <option value="{{ $method }}" @selected($filters['payment_method'] === $method)>{{ $label }}</option>
            @endforeach
        </select>
        <input type="text" name="search" class="sfp-input" style="margin-bottom:0;max-width:220px" placeholder="Search description" value="{{ $filters['search'] }}">
        <button type="submit" class="sfp-btn-primary">Filter</button>
        <a href="{{ $tenantUrl->route('expenses.index') }}" class="sfp-btn-outline">Reset</a>
    </form>

    <div class="sfp-table-wrap">
        <div class="sfp-table-head-row" style="grid-template-columns:1fr 140px 120px 110px 150px 80px">
            <span>Description</span>
            <span>Category</span>
            <span>Amount</span>
            <span>Expense date</span>
            <span>Entered on</span>
            <span></span>
        </div>

        @forelse ($expenses as $expense)
            <div class="sfp-table-row" style="grid-template-columns:1fr 140px 120px 110px 150px 80px">
                <div>
                    <div style="font-size:14.5px">{{ $expense->description }}</div>
                    @if ($expense->is_recurring)
                        <span class="sfp-pill sfp-pill-purple" style="margin-top:4px">{{ ucfirst($expense->recurrence_interval) }}</span>
                    @endif
                </div>
                <span style="font-size:13.5px;color:#66736F">{{ $expense->category?->name ?: 'Uncategorised' }}</span>
                <span class="sfp-mono" style="font-size:13.5px">&#8377;{{ number_format($expense->amount, 2) }}</span>
                <span style="font-size:13.5px;color:#66736F">{{ $expense->expense_date->format('d M Y') }}</span>
                <span style="font-size:13.5px;color:#66736F">{{ $expense->created_at->format('d M Y, h:i A') }}</span>
                <a href="{{ $tenantUrl->route('expenses.show', $expense) }}" style="font-size:12.5px;color:#1B4B8F">View</a>
            </div>
        @empty
            <div class="sfp-table-row" style="grid-template-columns:1fr">
                <p style="color:#66736F;margin:0">No expenses match these filters.</p>
            </div>
        @endforelse
    </div>
@endsection
