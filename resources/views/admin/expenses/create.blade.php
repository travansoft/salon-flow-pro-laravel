@extends('layouts.admin')

@section('title', 'Add Expense')

@section('content')
    <div class="sfp-page-header">
        <div>
            <h1 class="sfp-page-title">Add expense</h1>
            <p class="sfp-page-subtitle">Record a one-off or recurring business expense.</p>
        </div>
    </div>

    <div class="sfp-card">
        <form action="{{ $tenantUrl->route('expenses.store') }}" method="POST" enctype="multipart/form-data">
            @csrf

            <div class="sfp-split-2">
                <div class="sfp-field">
                    <label class="sfp-label">Description</label>
                    <input type="text" name="description" class="sfp-input" value="{{ old('description') }}">
                    @error('description')
                        <span class="sfp-invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>

                <div class="sfp-field">
                    <label class="sfp-label">Amount</label>
                    <input type="number" step="0.01" min="0.01" name="amount" class="sfp-input" value="{{ old('amount') }}">
                    @error('amount')
                        <span class="sfp-invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>
            </div>

            <div class="sfp-field">
                <label class="sfp-label">Paid via</label>
                <select name="payment_method" class="sfp-select">
                    @foreach (['cash' => 'Cash', 'upi' => 'UPI', 'card' => 'Card'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('payment_method', 'cash') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('payment_method')
                    <span class="sfp-invalid-feedback">{{ $message }}</span>
                @enderror
            </div>

            <div class="sfp-field">
                <label class="sfp-label">Category</label>
                <select name="category_id" class="sfp-select">
                    <option value="">No category</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected(old('category_id') == $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
                @error('category_id')
                    <span class="sfp-invalid-feedback">{{ $message }}</span>
                @enderror
                <a href="{{ $tenantUrl->route('expenseCategories.create') }}" class="sfp-action-link" style="font-size:12px">+ Add a new category</a>
            </div>

            <div class="sfp-split-2">
                <div class="sfp-field">
                    <label class="sfp-label">Expense date</label>
                    <input type="date" name="expense_date" class="sfp-input" value="{{ old('expense_date', now()->format('Y-m-d')) }}">
                    @error('expense_date')
                        <span class="sfp-invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>

                <div class="sfp-field">
                    <label class="sfp-label">Receipt</label>
                    <input type="file" name="receipt" class="sfp-input" accept=".jpg,.jpeg,.png,.pdf">
                    @error('receipt')
                        <span class="sfp-invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>
            </div>

            <div class="sfp-field" style="display:flex;align-items:center;gap:10px">
                <input type="checkbox" name="is_recurring" value="1" id="is_recurring" @checked(old('is_recurring')) onchange="document.getElementById('recurrence_interval_field').style.display = this.checked ? 'block' : 'none'">
                <label class="sfp-label" for="is_recurring" style="margin-bottom:0">Recurring expense</label>
                @error('is_recurring')
                    <span class="sfp-invalid-feedback">{{ $message }}</span>
                @enderror
            </div>

            <div class="sfp-field" id="recurrence_interval_field" style="display:{{ old('is_recurring') ? 'block' : 'none' }}">
                <label class="sfp-label">Recurs</label>
                <select name="recurrence_interval" class="sfp-select">
                    <option value="">Select interval</option>
                    <option value="weekly" @selected(old('recurrence_interval') === 'weekly')>Weekly</option>
                    <option value="monthly" @selected(old('recurrence_interval') === 'monthly')>Monthly</option>
                    <option value="yearly" @selected(old('recurrence_interval') === 'yearly')>Yearly</option>
                </select>
                @error('recurrence_interval')
                    <span class="sfp-invalid-feedback">{{ $message }}</span>
                @enderror
            </div>

            <div class="sfp-form-actions">
                <button type="submit" class="sfp-btn-primary">Save</button>
                <a href="{{ $tenantUrl->route('expenses.index') }}" class="sfp-btn-outline">Cancel</a>
            </div>
        </form>
    </div>
@endsection
