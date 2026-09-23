@extends('layouts.admin')

@section('title', 'New Bill')

@section('content')
    <div class="sfp-page-header">
        <div>
            <h1 class="sfp-page-title">New bill</h1>
            <p class="sfp-page-subtitle">Search a service by name or code. Press Enter on an exact code for the fastest add.</p>
        </div>
    </div>

    <div class="sfp-card">
        <form id="bill-form">
            @csrf

            <div class="sfp-field sfp-autosuggest">
                <label class="sfp-label" for="bill-client-search">Client name</label>
                <input type="text" id="bill-client-search" class="sfp-input" autocomplete="off" placeholder="Search or type a new client&hellip; (blank = walk-in)">
                <input type="hidden" name="client_id" id="bill-client-id">
                <div id="bill-client-suggestions" class="sfp-suggestions" hidden></div>
                <div id="bill-client-feedback" style="font-size:12.5px;margin-top:6px;color:#66736F"></div>
                @error('client_id')
                    <span class="sfp-invalid-feedback">{{ $message }}</span>
                @enderror
            </div>

            <div class="sfp-split-2">
                <div class="sfp-field sfp-autosuggest">
                    <label class="sfp-label" for="bill-client-phone">Mobile number <span style="color:#94A19D;font-weight:400">(optional)</span></label>
                    <input type="text" id="bill-client-phone" class="sfp-input" autocomplete="off">
                    <div id="bill-client-phone-suggestions" class="sfp-suggestions" hidden></div>
                    @error('client_phone')
                        <span class="sfp-invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>

                <div class="sfp-field">
                    <label class="sfp-label" for="bill-client-gst">Client GSTIN <span style="color:#94A19D;font-weight:400">(optional)</span></label>
                    <input type="text" id="bill-client-gst" class="sfp-input" autocomplete="off">
                    @error('client_gst_number')
                        <span class="sfp-invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>
            </div>

            <div class="sfp-field sfp-autosuggest">
                <label class="sfp-label" for="bill-item-search">Add service</label>
                <input type="text" id="bill-item-search" class="sfp-input" autocomplete="off" placeholder="Type a service name or code, press Enter to add exact code">
                <div id="bill-item-suggestions" class="sfp-suggestions" hidden></div>
            </div>

            <div class="sfp-field">
                <label class="sfp-label">Line items</label>
                <div class="sfp-table-wrap">
                    <div class="sfp-table-head-row" style="grid-template-columns:1.5fr 1.3fr 64px 100px 40px">
                        <span>Item</span>
                        <span>Staff</span>
                        <span style="text-align:center">Qty</span>
                        <span style="text-align:right">Price</span>
                        <span></span>
                    </div>
                    <div id="bill-items"></div>
                    <div id="bill-items-empty" class="sfp-table-row" style="grid-template-columns:1fr">
                        <span style="color:#94A19D;font-size:13.5px">No items added yet.</span>
                    </div>
                </div>
                @error('items')
                    <span class="sfp-invalid-feedback">{{ $message }}</span>
                @enderror
            </div>

            <div class="sfp-split-2">
                <div class="sfp-field">
                    <label class="sfp-label" for="bill-discount">Discount <span style="color:#94A19D;font-weight:400">(optional, %)</span></label>
                    <input type="number" id="bill-discount" class="sfp-input" min="0" max="100" step="0.01" placeholder="0">
                </div>
            </div>

            <div style="display:flex;justify-content:space-between;font-size:13.5px;color:#66736F;padding-top:4px">
                <span>Subtotal</span>
                <span id="bill-subtotal" class="sfp-mono">&#8377;0.00</span>
            </div>
            <div id="bill-discount-row" style="display:none;justify-content:space-between;font-size:13.5px;color:#A8506B;padding-top:4px">
                <span>Discount</span>
                <span id="bill-discount-amount" class="sfp-mono">&minus;&#8377;0.00</span>
            </div>

            <div style="display:flex;justify-content:space-between;align-items:baseline;padding-top:16px;margin-top:8px;border-top:1px solid #EDF1F0">
                <span style="font-size:15px">Total</span>
                <span id="bill-total" class="sfp-heading" style="font-size:26px">&#8377;0.00</span>
            </div>

            <div class="sfp-field" style="margin-top:16px">
                <label class="sfp-label">Payment method &mdash; press 1, 2, or 3</label>
                <div style="display:flex;gap:8px">
                    <button type="button" class="sfp-btn-outline bill-method" data-method="cash" style="flex:1">1 &middot; Cash</button>
                    <button type="button" class="sfp-btn-outline bill-method" data-method="card" style="flex:1">2 &middot; Card</button>
                    <button type="button" class="sfp-btn-outline bill-method active" data-method="upi" style="flex:1">3 &middot; UPI</button>
                </div>
            </div>

            <div id="bill-feedback" style="font-size:13px;margin:10px 0;min-height:18px"></div>

            <div class="sfp-form-actions">
                <button type="button" id="bill-settle" class="sfp-btn-primary">Create bill</button>
                <a href="{{ $tenantUrl->route('bills.index') }}" class="sfp-btn-outline">Cancel</a>
            </div>
        </form>
    </div>
@endsection

@section('styles')
<style>
    .sfp-autosuggest {
        position: relative;
    }

    .sfp-autosuggest .sfp-input {
        transition: border-color .15s ease, box-shadow .15s ease;
    }

    .sfp-autosuggest .sfp-input:focus {
        border-color: #1B4B8F;
        box-shadow: 0 0 0 3px rgba(27,75,143,.12);
        outline: none;
    }

    .sfp-suggestions {
        position: absolute;
        z-index: 20;
        left: 0;
        right: 0;
        top: calc(100% + 4px);
        background: #fff;
        border: 1px solid #E3EAE8;
        border-radius: 10px;
        box-shadow: 0 10px 24px rgba(16,24,22,.1);
        max-height: 280px;
        overflow-y: auto;
        padding: 6px;
        animation: sfp-suggestions-in .12s ease-out;
    }

    @keyframes sfp-suggestions-in {
        from { opacity: 0; transform: translateY(-4px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .sfp-suggestion-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        padding: 9px 10px;
        font-size: 13.5px;
        border-radius: 7px;
        cursor: pointer;
        transition: background-color .1s ease;
    }

    .sfp-suggestion-item:hover,
    .sfp-suggestion-item.active {
        background: #F1F6F4;
    }

    .sfp-suggestion-item .sfp-suggestion-main {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .sfp-suggestion-item small {
        color: #94A19D;
        white-space: nowrap;
        flex: none;
    }

    .sfp-suggestion-empty {
        padding: 10px;
        font-size: 13px;
        color: #94A19D;
    }
</style>
@endsection

@section('scripts')
<script>
(function () {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
    const eligibleStaffUrlTemplate = '{{ $tenantUrl->route('services.eligibleStaff', '__SERVICE_ID__') }}';

    const clientSearch = document.getElementById('bill-client-search');
    const clientIdInput = document.getElementById('bill-client-id');
    const clientPhoneInput = document.getElementById('bill-client-phone');
    const clientGstInput = document.getElementById('bill-client-gst');
    const clientSuggestions = document.getElementById('bill-client-suggestions');
    const clientPhoneSuggestions = document.getElementById('bill-client-phone-suggestions');
    const clientFeedback = document.getElementById('bill-client-feedback');

    const itemSearch = document.getElementById('bill-item-search');
    const itemSuggestions = document.getElementById('bill-item-suggestions');

    const itemsBox = document.getElementById('bill-items');
    const itemsEmpty = document.getElementById('bill-items-empty');
    const subtotalEl = document.getElementById('bill-subtotal');
    const discountInput = document.getElementById('bill-discount');
    const discountRow = document.getElementById('bill-discount-row');
    const discountAmountEl = document.getElementById('bill-discount-amount');
    const totalEl = document.getElementById('bill-total');

    const methodButtons = [...document.querySelectorAll('.bill-method')];
    const feedback = document.getElementById('bill-feedback');
    const settleBtn = document.getElementById('bill-settle');

    let lines = [];
    let lineSeq = 0;
    let paymentMethod = 'upi';
    let clientSelection = null;

    const money = (n) => '₹' + Number(n || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    function debounce(fn, delay) {
        let timer = null;
        return (...args) => {
            clearTimeout(timer);
            timer = setTimeout(() => fn(...args), delay);
        };
    }

    function setFeedback(message, isError) {
        feedback.textContent = message || '';
        feedback.style.color = isError ? '#A8506B' : '#66736F';
    }

    function hideSuggestions(box) {
        box.hidden = true;
        box.innerHTML = '';
    }

    function renderSuggestions(box, items, renderLabel, onPick, emptyMessage) {
        if (!items.length) {
            if (emptyMessage) {
                box.innerHTML = `<div class="sfp-suggestion-empty">${emptyMessage}</div>`;
                box.hidden = false;
                return;
            }
            hideSuggestions(box);
            return;
        }

        box.innerHTML = '';
        items.forEach((item, index) => {
            const row = document.createElement('div');
            row.className = 'sfp-suggestion-item' + (index === 0 ? ' active' : '');
            row.innerHTML = renderLabel(item);
            row.addEventListener('mousedown', (event) => {
                event.preventDefault();
                onPick(item);
            });
            box.appendChild(row);
        });
        box.hidden = false;
    }

    function moveActiveSuggestion(box, direction) {
        const items = [...box.querySelectorAll('.sfp-suggestion-item')];
        if (!items.length) {
            return;
        }

        let index = items.findIndex((item) => item.classList.contains('active'));
        items[index]?.classList.remove('active');
        index = (index + direction + items.length) % items.length;
        items[index].classList.add('active');
        items[index].scrollIntoView({ block: 'nearest' });
    }

    function pickActiveSuggestion(box) {
        return box.querySelector('.sfp-suggestion-item.active');
    }

    // ----- Client search -----

    async function searchClients(term) {
        const response = await fetch('{{ $tenantUrl->route("clients.search") }}?q=' + encodeURIComponent(term), {
            headers: { 'Accept': 'application/json' },
        });

        if (!response.ok) {
            return [];
        }

        const data = await response.json();
        return data.clients || [];
    }

    function selectClient(client) {
        clientSelection = client;
        clientIdInput.value = client.id;
        clientSearch.value = client.name;
        clientPhoneInput.value = client.phone || '';
        clientGstInput.value = client.gst_number || '';
        clientFeedback.textContent = '';
        hideSuggestions(clientSuggestions);
        hideSuggestions(clientPhoneSuggestions);
    }

    function clearClientSelection() {
        if (clientSelection) {
            clientSelection = null;
            clientIdInput.value = '';
        }
    }

    function setupClientSearchField(input, suggestionsBox, renderLabel) {
        const debouncedSearch = debounce(async (term) => {
            if (!term) {
                hideSuggestions(suggestionsBox);
                return;
            }

            const clients = await searchClients(term);
            renderSuggestions(suggestionsBox, clients, renderLabel, selectClient, `No clients matching "${term}" &mdash; fill the fields below to add a new client.`);
        }, 250);

        input.addEventListener('input', () => {
            clearClientSelection();
            debouncedSearch(input.value.trim());
        });

        input.addEventListener('focus', () => {
            if (input.value.trim()) {
                debouncedSearch(input.value.trim());
            }
        });

        input.addEventListener('keydown', (event) => {
            if (event.key === 'ArrowDown') {
                event.preventDefault();
                moveActiveSuggestion(suggestionsBox, 1);
            }
            if (event.key === 'ArrowUp') {
                event.preventDefault();
                moveActiveSuggestion(suggestionsBox, -1);
            }
            if (event.key === 'Enter') {
                event.preventDefault();
                const active = pickActiveSuggestion(suggestionsBox);
                if (active) {
                    active.dispatchEvent(new Event('mousedown'));
                    return;
                }
                if (input === clientSearch && input.value.trim() === '' && clientPhoneInput.value.trim() === '') {
                    clientFeedback.textContent = 'Walk-in customer.';
                }
                itemSearch.focus();
            }
            if (event.key === 'Escape') {
                hideSuggestions(suggestionsBox);
            }
        });

        input.addEventListener('blur', () => setTimeout(() => hideSuggestions(suggestionsBox), 150));
    }

    setupClientSearchField(clientSearch, clientSuggestions, (client) => `
        <span class="sfp-suggestion-main">${client.name}</span>
        <small>${client.phone || ''}</small>
    `);

    setupClientSearchField(clientPhoneInput, clientPhoneSuggestions, (client) => `
        <span class="sfp-suggestion-main">${client.phone || ''}</span>
        <small>${client.name}</small>
    `);

    clientGstInput.addEventListener('input', clearClientSelection);

    // ----- Item search -----

    async function searchServices(term) {
        const response = await fetch('{{ $tenantUrl->route("services.search") }}?q=' + encodeURIComponent(term), {
            headers: { 'Accept': 'application/json' },
        });

        if (!response.ok) {
            return [];
        }

        const data = await response.json();
        return data.services || [];
    }

    const debouncedItemSearch = debounce(async (term) => {
        if (!term) {
            hideSuggestions(itemSuggestions);
            return;
        }

        const services = await searchServices(term);
        renderSuggestions(itemSuggestions, services, (service) => `
            <span class="sfp-suggestion-main">${service.name} <small>(${service.code})</small></span>
            <small>${money(service.price_inclusive)}</small>
        `, addServiceLine, `No active service matching "${term}".`);
    }, 250);

    itemSearch.addEventListener('input', () => {
        debouncedItemSearch(itemSearch.value.trim());
    });

    itemSearch.addEventListener('focus', () => {
        if (itemSearch.value.trim()) {
            debouncedItemSearch(itemSearch.value.trim());
        }
    });

    itemSearch.addEventListener('keydown', async (event) => {
        if (event.key === 'ArrowDown') {
            event.preventDefault();
            moveActiveSuggestion(itemSuggestions, 1);
            return;
        }
        if (event.key === 'ArrowUp') {
            event.preventDefault();
            moveActiveSuggestion(itemSuggestions, -1);
            return;
        }
        if (event.key === 'Escape') {
            hideSuggestions(itemSuggestions);
            return;
        }
        if (event.key !== 'Enter') {
            return;
        }

        event.preventDefault();

        const active = pickActiveSuggestion(itemSuggestions);
        if (active) {
            active.dispatchEvent(new Event('mousedown'));
            return;
        }

        const term = itemSearch.value.trim();
        if (term === '') {
            return;
        }

        const services = await searchServices(term);
        const exact = services.find((service) => String(service.code) === term);

        if (exact) {
            addServiceLine(exact);
            return;
        }

        setFeedback('No active service matches "' + term + '".', true);
    });

    itemSearch.addEventListener('blur', () => setTimeout(() => hideSuggestions(itemSuggestions), 150));

    // ----- Line items -----

    function addServiceLine(service) {
        const requiresRateConfirmation = Boolean(service.requires_rate_confirmation);

        lines.push({
            id: lineSeq++,
            serviceId: service.id,
            description: service.name,
            priceInclusive: Number(service.price_inclusive),
            taxRate: Number(service.tax_rate),
            quantity: 1,
            staffProfileId: null,
            requiresRateConfirmation,
        });
        renderLines();
        itemSearch.value = '';
        hideSuggestions(itemSuggestions);

        if (requiresRateConfirmation) {
            setFeedback(`${service.name} needs the consultation rate — confirm the price before billing.`, true);
            itemsBox.querySelector(`[data-line-id="${lines[lines.length - 1].id}"] .bill-line-price`)?.focus();
        } else {
            itemSearch.focus();
            setFeedback('', false);
        }
    }

    async function loadEligibleStaff(line, select) {
        if (!line.serviceId) {
            select.innerHTML = '<option value="">No staff assigned</option>';
            return;
        }

        select.innerHTML = '<option value="">Loading&hellip;</option>';

        const response = await fetch(eligibleStaffUrlTemplate.replace('__SERVICE_ID__', encodeURIComponent(line.serviceId)), {
            headers: { 'Accept': 'application/json' },
        });

        const data = response.ok ? await response.json() : { staff: [] };
        const staff = data.staff || [];

        select.innerHTML = '<option value="">Select staff&hellip;</option>' + staff.map((member) =>
            `<option value="${member.id}" ${String(member.id) === String(line.staffProfileId || '') ? 'selected' : ''}>${member.name}</option>`
        ).join('');
    }

    function renderLines() {
        itemsBox.innerHTML = '';
        itemsEmpty.style.display = lines.length ? 'none' : 'grid';

        lines.forEach((line) => {
            const row = document.createElement('div');
            row.className = 'sfp-table-row';
            row.dataset.lineId = line.id;
            row.style.gridTemplateColumns = '1.5fr 1.3fr 64px 100px 40px';

            row.innerHTML = `
                <span class="bill-line-description-wrap">
                    <span style="font-size:14px">${line.description}</span>
                    ${line.requiresRateConfirmation ? '<span class="sfp-pill sfp-pill-amber" style="margin-left:6px;font-size:10.5px">Confirm rate</span>' : ''}
                </span>
                <span></span>
                <input type="number" min="1" value="${line.quantity}" class="sfp-table-control sfp-table-control--qty bill-line-qty">
                <input type="number" min="0" step="0.01" value="${line.priceInclusive}" class="sfp-table-control bill-line-price" style="text-align:right${line.requiresRateConfirmation ? ';border-color:#C98A2C' : ''}">
                <button type="button" class="sfp-table-remove bill-line-remove" title="Remove item" aria-label="Remove item">&times;</button>
            `;

            const staffSelect = document.createElement('select');
            staffSelect.className = 'sfp-table-control bill-line-staff';
            staffSelect.addEventListener('change', () => {
                line.staffProfileId = staffSelect.value || null;
            });
            row.children[1].replaceWith(staffSelect);
            loadEligibleStaff(line, staffSelect);

            row.querySelector('.bill-line-qty').addEventListener('input', (event) => {
                line.quantity = Math.max(1, parseInt(event.target.value, 10) || 1);
                updateTotal();
            });

            row.querySelector('.bill-line-price').addEventListener('input', (event) => {
                const value = parseFloat(event.target.value);
                line.priceInclusive = Number.isFinite(value) && value >= 0 ? value : 0;
                updateTotal();
            });

            row.querySelector('.bill-line-remove').addEventListener('click', () => {
                lines = lines.filter((l) => l.id !== line.id);
                renderLines();
            });

            itemsBox.appendChild(row);
        });

        updateTotal();
    }

    function discountPercent() {
        const value = parseFloat(discountInput.value);

        return Number.isFinite(value) && value >= 0 && value <= 100 ? value : 0;
    }

    function updateTotal() {
        const subtotal = lines.reduce((sum, line) => sum + (Number(line.priceInclusive) * Number(line.quantity)), 0);
        const discount = subtotal * (discountPercent() / 100);
        const total = subtotal - discount;

        subtotalEl.textContent = money(subtotal);
        discountRow.style.display = discount > 0 ? 'flex' : 'none';
        discountAmountEl.textContent = '−' + money(discount);
        totalEl.textContent = money(total);
    }

    discountInput.addEventListener('input', updateTotal);

    // ----- Payment method -----

    function selectMethod(method) {
        paymentMethod = method;
        methodButtons.forEach((btn) => btn.classList.toggle('active', btn.dataset.method === method));
    }

    methodButtons.forEach((btn) => {
        btn.addEventListener('click', () => selectMethod(btn.dataset.method));
    });

    document.addEventListener('keydown', (event) => {
        const active = document.activeElement;
        const inFormField = active === clientSearch || active === clientPhoneInput || active === clientGstInput || active === itemSearch || active === discountInput || active?.classList?.contains('bill-line-qty');

        if (!inFormField && (event.key === '1' || event.key === '2' || event.key === '3')) {
            const map = { '1': 'cash', '2': 'card', '3': 'upi' };
            selectMethod(map[event.key]);
        }
    });

    // ----- Submission -----

    function buildClientPayload() {
        return {
            client_id: clientIdInput.value || null,
            client_name: clientSearch.value.trim() || null,
            client_phone: clientPhoneInput.value.trim() || null,
            client_gst_number: clientGstInput.value.trim() || null,
            discount_percent: discountPercent(),
        };
    }

    function buildItemsPayload() {
        return lines.map((line) => ({
            service_id: line.serviceId,
            staff_profile_id: line.staffProfileId || null,
            description: line.description,
            quantity: line.quantity,
            unit_price: line.priceInclusive,
            tax_rate: line.taxRate,
        }));
    }

    function validateLines() {
        if (lines.length === 0) {
            setFeedback('Add at least one item before creating the bill.', true);
            return false;
        }

        const unconfirmed = lines.find((line) => line.requiresRateConfirmation && !(Number(line.priceInclusive) > 0));

        if (unconfirmed) {
            setFeedback(`Enter the consultation rate for ${unconfirmed.description} before billing.`, true);
            itemsBox.querySelector(`[data-line-id="${unconfirmed.id}"] .bill-line-price`)?.focus();
            return false;
        }

        return true;
    }

    async function createAndSettle() {
        if (!validateLines()) {
            return;
        }

        settleBtn.disabled = true;
        setFeedback('Settling…', false);

        try {
            const response = await fetch('{{ $tenantUrl->route("bills.settle") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({
                    ...buildClientPayload(),
                    items: buildItemsPayload(),
                    payment_method: paymentMethod,
                }),
            });

            const data = await response.json();

            if (!response.ok) {
                setFeedback(data.message || 'Could not settle the bill.', true);
                settleBtn.disabled = false;
                return;
            }

            setFeedback('Bill ' + data.bill_number + ' settled. Redirecting…', false);
            window.location.href = data.redirect;
        } catch (error) {
            setFeedback('Network error settling the bill.', true);
            settleBtn.disabled = false;
        }
    }

    settleBtn.addEventListener('click', createAndSettle);

    renderLines();
})();
</script>
@endsection
