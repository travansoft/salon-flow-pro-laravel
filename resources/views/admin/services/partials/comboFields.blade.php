@php
    $comboRows = old('combo_items', isset($service)
        ? $service->comboItems->map(fn ($item) => ['service_id' => $item->component_service_id, 'price' => $item->price])->all()
        : []);
    $isCombo = (bool) old('is_combo', isset($service) ? $service->is_combo : false);
@endphp

<div class="sfp-field" style="display:flex;align-items:center;gap:10px;margin-bottom:18px">
    <input type="hidden" name="is_combo" value="0">
    <input type="checkbox" name="is_combo" value="1" id="is-combo" @checked($isCombo)>
    <label class="sfp-label" for="is-combo" style="margin-bottom:0"><i class="bi bi-collection"></i> This is a combo &mdash; a package of several services sold at one price</label>
</div>

<div id="combo-panel" style="display:none;margin-bottom:24px">
    <div class="sfp-card-title">Combo services</div>
    <p style="color:#66736F;font-size:13px;margin-top:-8px">Pick the services in this combo and the price of each. The combo price defaults to their total; you can override it above. Staff are chosen per service when billing.</p>

    <div id="combo-rows"></div>

    <div style="display:flex;align-items:center;gap:14px;margin-top:10px;flex-wrap:wrap">
        <button type="button" id="combo-add-row" class="sfp-btn-outline"><i class="bi bi-plus-lg"></i> Add service</button>
        <span style="font-size:13px;color:#66736F">Components total: <strong class="sfp-mono" id="combo-sum">₹0.00</strong></span>
        <button type="button" id="combo-reset-price" class="sfp-action-link" style="display:none;font-size:12.5px;background:none;border:0">Override active &ndash; reset price to total</button>
    </div>

    @error('combo_items')
        <span class="sfp-invalid-feedback">{{ $message }}</span>
    @enderror
    @foreach ($errors->get('combo_items.*') as $messages)
        @foreach ($messages as $message)
            <span class="sfp-invalid-feedback">{{ $message }}</span>
        @endforeach
    @endforeach
</div>

<script>
(function () {
    const components = @json($componentServices->map(fn ($componentService) => ['id' => $componentService->id, 'name' => $componentService->category ? "{$componentService->name} - {$componentService->category->name}" : $componentService->name, 'price' => (float) $componentService->price])->values());
    const initialRows = @json(array_values($comboRows));

    const checkbox = document.getElementById('is-combo');
    const panel = document.getElementById('combo-panel');
    const staffPanel = document.getElementById('eligible-staff-panel');
    const rowsEl = document.getElementById('combo-rows');
    const sumEl = document.getElementById('combo-sum');
    const resetBtn = document.getElementById('combo-reset-price');
    const priceInput = document.getElementById('service-price-inclusive');
    let priceOverridden = false;
    let nextIndex = 0;

    function money(n) {
        return '₹' + Number(n || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function total() {
        return Array.from(rowsEl.querySelectorAll('[data-combo-price]'))
            .reduce((sum, input) => sum + (parseFloat(input.value) || 0), 0);
    }

    function syncPrice() {
        const sum = total();
        sumEl.textContent = money(sum);

        if (!checkbox.checked) {
            return;
        }

        if (!priceOverridden) {
            priceInput.value = sum.toFixed(2);
            priceInput.dispatchEvent(new Event('input'));
        }

        resetBtn.style.display = priceOverridden && Math.abs(parseFloat(priceInput.value) - sum) > 0.004 ? 'inline' : 'none';
    }

    function addRow(row) {
        const index = nextIndex++;
        const wrapper = document.createElement('div');
        wrapper.className = 'sfp-split-2';
        wrapper.style.cssText = 'grid-template-columns:2fr 1fr auto;align-items:end;margin-bottom:8px';

        const options = ['<option value="">Select service</option>']
            .concat(components.map((c) => `<option value="${c.id}" data-price="${c.price}">${c.name.replace(/</g, '&lt;')}</option>`))
            .join('');

        wrapper.innerHTML = `
            <div class="sfp-field" style="margin:0"><select name="combo_items[${index}][service_id]" class="sfp-select" data-combo-service>${options}</select></div>
            <div class="sfp-field" style="margin:0"><input type="number" step="0.01" min="0" name="combo_items[${index}][price]" class="sfp-input" data-combo-price placeholder="Price (incl. GST)"></div>
            <button type="button" class="sfp-btn-outline" data-combo-remove aria-label="Remove"><i class="bi bi-trash"></i></button>`;

        const select = wrapper.querySelector('[data-combo-service]');
        const price = wrapper.querySelector('[data-combo-price]');

        if (row) {
            select.value = String(row.service_id);
            price.value = row.price;
        }

        select.addEventListener('change', () => {
            price.value = select.selectedOptions[0].dataset.price || '';
            syncPrice();
        });
        price.addEventListener('input', syncPrice);
        wrapper.querySelector('[data-combo-remove]').addEventListener('click', () => {
            wrapper.remove();
            syncPrice();
        });

        rowsEl.appendChild(wrapper);
    }

    function toggle() {
        panel.style.display = checkbox.checked ? 'block' : 'none';

        if (staffPanel) {
            staffPanel.style.display = checkbox.checked ? 'none' : 'block';
        }

        if (checkbox.checked && rowsEl.children.length === 0) {
            addRow(null);
            addRow(null);
        }

        syncPrice();
    }

    document.getElementById('combo-add-row').addEventListener('click', () => addRow(null));
    checkbox.addEventListener('change', toggle);

    priceInput.addEventListener('input', (event) => {
        if (event.isTrusted && checkbox.checked) {
            priceOverridden = true;
            syncPrice();
        }
    });

    resetBtn.addEventListener('click', () => {
        priceOverridden = false;
        syncPrice();
    });

    initialRows.forEach(addRow);

    if (checkbox.checked) {
        priceOverridden = initialRows.length > 0 && Math.abs((parseFloat(priceInput.value) || 0) - total()) > 0.004;
    }

    toggle();
})();
</script>
