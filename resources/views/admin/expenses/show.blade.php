@extends('layouts.admin')

@section('title', $expense->description)

@section('content')
    <div class="sfp-page-header">
        <div>
            <h1 class="sfp-page-title">{{ $expense->description }}</h1>
            <p class="sfp-page-subtitle">
                {{ $expense->category?->name ?: 'Uncategorised' }}
                &middot; Recorded by {{ $expense->createdBy?->name ?? '—' }}
            </p>
        </div>
        <div class="sfp-row">
            @can('expenses.edit')
                <a href="{{ $tenantUrl->route('expenses.edit', $expense) }}" class="sfp-btn-outline">Edit expense</a>
            @endcan
            @can('expenses.delete')
                <form action="{{ $tenantUrl->route('expenses.destroy', $expense) }}" method="POST">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="sfp-btn-link-danger">Remove</button>
                </form>
            @endcan
        </div>
    </div>

    <div class="sfp-card" style="margin-bottom:16px">
        <div style="display:flex;align-items:center;gap:28px;flex-wrap:wrap">
            <div>
                <div class="sfp-label" style="margin-bottom:6px">Amount</div>
                <div class="sfp-mono" style="font-size:20px">&#8377;{{ number_format($expense->amount, 2) }}</div>
            </div>
            <div>
                <div class="sfp-label" style="margin-bottom:6px">Expense date</div>
                <div class="sfp-mono" style="font-size:20px">{{ $expense->expense_date->format('d M Y') }}</div>
            </div>
            <div>
                <div class="sfp-label" style="margin-bottom:6px">Entered on</div>
                <div class="sfp-mono" style="font-size:20px">{{ $expense->created_at->format('d M Y, h:i A') }}</div>
            </div>
            <div>
                <div class="sfp-label" style="margin-bottom:6px">Type</div>
                @if ($expense->is_recurring)
                    <span class="sfp-pill sfp-pill-purple">{{ ucfirst($expense->recurrence_interval) }}</span>
                @else
                    <span class="sfp-pill sfp-pill-neutral">One-off</span>
                @endif
            </div>
            @if ($expense->receipt_path)
                <div>
                    <div class="sfp-label" style="margin-bottom:6px">Receipt</div>
                    <span class="sfp-pill sfp-pill-sage">Attached</span>
                </div>
            @endif
        </div>
    </div>
@endsection
