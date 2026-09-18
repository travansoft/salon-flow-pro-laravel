@extends('layouts.admin')

@section('title', 'Edit Service')

@section('content')
    <div class="sfp-page-header">
        <div>
            <h1 class="sfp-page-title">Edit {{ $service->name }}</h1>
            <p class="sfp-page-subtitle">Changes to price apply going forward. Past bills keep the price charged at the time.</p>
        </div>
    </div>

    <div class="sfp-card">
        <form action="{{ $tenantUrl->route('services.update', $service) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="sfp-split-2">
                <div class="sfp-field">
                    <label class="sfp-label">Name</label>
                    <input type="text" name="name" class="sfp-input" value="{{ old('name', $service->name) }}">
                    @error('name')
                        <span class="sfp-invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>

                <div class="sfp-field">
                    <label class="sfp-label">POS code</label>
                    <input type="text" name="code" class="sfp-input" value="{{ old('code', $service->code) }}" placeholder="e.g. 101">
                    @error('code')
                        <span class="sfp-invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>
            </div>

            <div class="sfp-field">
                <label class="sfp-label">Category</label>
                <select name="category_id" class="sfp-select">
                    <option value="">No category</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected(old('category_id', $service->category_id) == $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
                @error('category_id')
                    <span class="sfp-invalid-feedback">{{ $message }}</span>
                @enderror
                <a href="{{ $tenantUrl->route('serviceCategories.create') }}" class="sfp-action-link" style="font-size:12px">+ Add a new category</a>
            </div>

            <div class="sfp-split-2">
                <div class="sfp-field">
                    <label class="sfp-label">Price (incl. GST)</label>
                    <input type="number" step="0.01" min="0" name="price" id="service-price-inclusive" class="sfp-input" value="{{ old('price', $service->price) }}">
                    <p id="service-price-breakdown" style="font-size:12.5px;color:#66736F;margin:6px 0 0"></p>
                    @error('price')
                        <span class="sfp-invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>

                <div class="sfp-field">
                    <label class="sfp-label">Duration (minutes)</label>
                    <input type="number" min="1" name="duration_minutes" class="sfp-input" value="{{ old('duration_minutes', $service->duration_minutes) }}">
                    @error('duration_minutes')
                        <span class="sfp-invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>
            </div>

            <div class="sfp-split-2">
                <div class="sfp-field">
                    <label class="sfp-label">GST rate (%)</label>
                    <input type="number" step="0.01" min="0" max="100" name="tax_rate" id="service-tax-rate" class="sfp-input" value="{{ old('tax_rate', $service->tax_rate) }}" placeholder="Uses tenant default if blank">
                    @error('tax_rate')
                        <span class="sfp-invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>

                <div class="sfp-field">
                    <label class="sfp-label">HSN/SAC code</label>
                    <input type="text" name="hsn_sac_code" class="sfp-input" value="{{ old('hsn_sac_code', $service->hsn_sac_code) }}">
                    @error('hsn_sac_code')
                        <span class="sfp-invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>
            </div>

            <div class="sfp-field" style="display:flex;align-items:center;gap:10px;margin-bottom:18px">
                <input type="checkbox" name="is_active" value="1" id="is_active" @checked(old('is_active', $service->is_active))>
                <label class="sfp-label" for="is_active" style="margin-bottom:0">Active</label>
                @error('is_active')
                    <span class="sfp-invalid-feedback">{{ $message }}</span>
                @enderror
            </div>

            <div class="sfp-card-title" style="margin-top: 24px;">Eligible staff</div>
            <p style="color: #66736F; font-size: 13px; margin-top: -8px;">Select which staff members can perform this service. Only mapped staff can be assigned to it when billing.</p>

            <div class="sfp-field">
                @foreach ($staff as $staffMember)
                    <div class="form-check">
                        <input type="checkbox" name="staff_ids[]" value="{{ $staffMember->id }}" id="staff-{{ $staffMember->id }}" class="form-check-input" @checked(collect(old('staff_ids', $service->staff->pluck('id')))->contains($staffMember->id))>
                        <label class="form-check-label" for="staff-{{ $staffMember->id }}">{{ $staffMember->name }}</label>
                    </div>
                @endforeach
                @error('staff_ids')
                    <span class="sfp-invalid-feedback">{{ $message }}</span>
                @enderror
            </div>

            <div class="sfp-form-actions">
                <button type="submit" class="sfp-btn-primary">Save</button>
                <a href="{{ $tenantUrl->route('services.show', $service) }}" class="sfp-btn-outline">Cancel</a>
            </div>
        </form>
    </div>
@endsection

@section('scripts')
<script>
(function () {
    const tenantDefaultGstRate = {{ (float) ($tenant->default_gst_rate ?? 18) }};

    const inclusiveInput = document.getElementById('service-price-inclusive');
    const taxRateInput = document.getElementById('service-tax-rate');
    const breakdown = document.getElementById('service-price-breakdown');

    function money(n) {
        return '₹' + Number(n || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function currentTaxRate() {
        const rate = parseFloat(taxRateInput.value);
        return Number.isFinite(rate) && rate >= 0 ? rate : tenantDefaultGstRate;
    }

    function recalculateFromInclusive() {
        const inclusive = parseFloat(inclusiveInput.value);

        if (!Number.isFinite(inclusive) || inclusive < 0) {
            breakdown.textContent = '';
            return;
        }

        const rate = currentTaxRate();
        const exclusive = inclusive / (1 + rate / 100);
        const gstAmount = inclusive - exclusive;

        breakdown.textContent = `Base price ${money(exclusive)} + GST (${rate}%) ${money(gstAmount)} = ${money(inclusive)}`;
    }

    inclusiveInput.addEventListener('input', recalculateFromInclusive);
    taxRateInput.addEventListener('input', recalculateFromInclusive);

    recalculateFromInclusive();
})();
</script>
@endsection
