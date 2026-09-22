@extends('layouts.admin')

@section('title', 'Expenses')

@section('content')
    <div class="sfp-page-header">
        <div>
            <h1 class="sfp-page-title">Expenses</h1>
            <p class="sfp-page-subtitle">Total for {{ $month->format('F Y') }}: <span class="sfp-mono">&#8377;{{ number_format((float) $total, 2) }}</span></p>
        </div>
        <div class="sfp-row">
            <form method="GET" style="margin:0">
                <input type="month" name="month" value="{{ $month->format('Y-m') }}" class="sfp-input" style="margin-bottom:0;max-width:200px" onchange="this.form.submit()">
            </form>
            @can('expenses.view')
                <a href="{{ $tenantUrl->route('expenseCategories.index') }}" class="sfp-btn-outline">Manage categories</a>
            @endcan
            @can('expenses.create')
                <a href="{{ $tenantUrl->route('expenses.create') }}" class="sfp-btn-pill-dark">+ Add expense</a>
            @endcan
        </div>
    </div>

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
                <p style="color:#66736F;margin:0">No expenses recorded for this month.</p>
            </div>
        @endforelse
    </div>
@endsection
