@extends('layouts.admin')

@section('title', 'New Bill')

@section('content')
    <div class="sfp-page-header">
        <div>
            <h1 class="sfp-page-title">New bill</h1>
            <p class="sfp-page-subtitle">Search a service by name or code, or add a manual item. Press Enter on an exact code for the fastest add.</p>
        </div>
    </div>

    <div class="sfp-card">
        <form id="bill-form">
            @csrf

            <div class="sfp-field">
                <label class="sfp-label" for="bill-client-search">Client</label>
                <select id="bill-client-search" name="client_id" placeholder="Search by phone or name&hellip; (blank = walk-in)"></select>
                <div id="bill-client-feedback" style="font-size:12.5px;margin-top:6px;color:#66736F"></div>
                @error('client_id')
                    <span class="sfp-invalid-feedback">{{ $message }}</span>
                @enderror
            </div>

            <div class="sfp-field">
                <label class="sfp-label" for="bill-item-search">Add service</label>
                <select id="bill-item-search" placeholder="Type a service name or code&hellip;"></select>
            </div>

            <div class="sfp-field">
                <label class="sfp-label">Line items</label>
                <div class="sfp-table-wrap">
                    <div class="sfp-table-head-row" style="grid-template-columns:1.6fr 1.2fr 70px 110px auto">
                        <span>Item</span>
                        <span>Staff</span>
                        <span>Qty</span>
                        <span style="text-align:right">Price</span>
                        <span></span>
                    </div>
                    <div id="bill-items"></div>
                    <div id="bill-items-empty" class="sfp-table-row" style="grid-template-columns:1fr">
                        <span style="color:#94A19D;font-size:13.5px">No items added yet.</span>
                    </div>
                </div>
                <button type="button" id="bill-add-manual" class="sfp-btn-outline" style="margin-top:10px">+ Add manual item</button>
                @error('items')
                    <span class="sfp-invalid-feedback">{{ $message }}</span>
                @enderror
            </div>

            <div style="display:flex;justify-content:space-between;align-items:baseline;padding-top:16px;margin-top:8px;border-top:1px solid #EDF1F0">
                <span style="font-size:15px">Total</span>
                <span id="bill-total" class="sfp-heading" style="font-size:26px">&#8377;0.00</span>
            </div>

            <div class="sfp-field" id="bill-payment-section" hidden style="margin-top:16px">
                <label class="sfp-label">Payment method &mdash; press 1, 2, or 3</label>
                <div style="display:flex;gap:8px">
                    <button type="button" class="sfp-btn-outline bill-method active" data-method="cash" style="flex:1">1 &middot; Cash</button>
                    <button type="button" class="sfp-btn-outline bill-method" data-method="card" style="flex:1">2 &middot; Card</button>
                    <button type="button" class="sfp-btn-outline bill-method" data-method="upi" style="flex:1">3 &middot; UPI</button>
                </div>
            </div>

            <div id="bill-feedback" style="font-size:13px;margin:10px 0;min-height:18px"></div>

            <div class="sfp-form-actions">
                <button type="button" id="bill-create" class="sfp-btn-outline">Create bill</button>
                <button type="button" id="bill-settle" class="sfp-btn-primary">Create &amp; settle</button>
                <a href="{{ $tenantUrl->route('bills.index') }}" class="sfp-btn-outline">Cancel</a>
            </div>
        </form>
    </div>
@endsection

@section('styles')
<link rel="stylesheet" href="{{ asset('admin/vendor/tom-select/tom-select.bootstrap5.min.css') }}">
<style>
    .ts-wrapper.single .ts-control {
        border-radius: 10px;
        border-color: #E3EAE8;
        padding: 9px 12px;
        font-size: 14px;
    }
    .ts-dropdown {
        border-color: #E3EAE8;
        border-radius: 8px;
        box-shadow: 0 6px 18px rgba(0,0,0,.08);
        font-size: 13.5px;
    }
    .ts-dropdown .option small,
    .ts-control small {
        color: #94A19D;
    }
</style>
@endsection

@section('scripts')
<script src="{{ asset('admin/vendor/tom-select/tom-select.complete.min.js') }}"></script>
<script>
(function () {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

    const clientFeedback = document.getElementById('bill-client-feedback');

    const itemsBox = document.getElementById('bill-items');
    const itemsEmpty = document.getElementById('bill-items-empty');
    const totalEl = document.getElementById('bill-total');
    const addManualBtn = document.getElementById('bill-add-manual');

    const paymentSection = document.getElementById('bill-payment-section');
    const methodButtons = [...document.querySelectorAll('.bill-method')];
    const feedback = document.getElementById('bill-feedback');
    const createBtn = document.getElementById('bill-create');
    const settleBtn = document.getElementById('bill-settle');

    let lines = [];
    let lineSeq = 0;
    let paymentMethod = 'cash';

    const money = (n) => '₹' + Number(n || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    function setFeedback(message, isError) {
        feedback.textContent = message || '';
        feedback.style.color = isError ? '#A8506B' : '#66736F';
    }

    // ----- Client search -----

    const clientSelect = new TomSelect('#bill-client-search', {
        valueField: 'id',
        labelField: 'name',
        searchField: ['name', 'phone'],
        options: [],
        create: false,
        load(term, callback) {
            if (!term) {
                clientFeedback.textContent = 'Walk-in customer.';
                return callback();
            }

            fetch('{{ $tenantUrl->route("clients.search") }}?q=' + encodeURIComponent(term), {
                headers: { 'Accept': 'application/json' },
            })
                .then((response) => (response.ok ? response.json() : { clients: [] }))
                .then((data) => callback(data.clients || []))
                .catch(() => callback());
        },
        render: {
            option(client, escape) {
                return `<div>${escape(client.name)} <small>${escape(client.phone || '')}</small></div>`;
            },
            item(client, escape) {
                return `<div>${escape(client.name)}${client.phone ? ' · ' + escape(client.phone) : ''}</div>`;
            },
            no_results() {
                return '<div class="no-results">No clients found.</div>';
            },
        },
        onChange() {
            clientFeedback.textContent = '';
        },
    });

    // ----- Item search -----

    const itemSelect = new TomSelect('#bill-item-search', {
        valueField: 'id',
        labelField: 'name',
        searchField: ['name', 'code'],
        options: [],
        create: false,
        load(term, callback) {
            if (!term) {
                return callback();
            }

            fetch('{{ $tenantUrl->route("services.search") }}?q=' + encodeURIComponent(term), {
                headers: { 'Accept': 'application/json' },
            })
                .then((response) => (response.ok ? response.json() : { services: [] }))
                .then((data) => callback(data.services || []))
                .catch(() => callback());
        },
        render: {
            option(service, escape) {
                return `<div>${escape(service.name)} <small>(${escape(service.code || '')})</small> <small>${escape(money(service.price))}</small></div>`;
            },
            no_results() {
                return '<div class="no-results">No services found.</div>';
            },
        },
        onChange(value) {
            if (!value) {
                return;
            }

            const service = itemSelect.options[value];
            addServiceLine(service);
            setTimeout(() => {
                itemSelect.clear(true);
                itemSelect.clearOptions();
            }, 0);
        },
    });

    // ----- Line items -----

    function addServiceLine(service) {
        lines.push({
            id: lineSeq++,
            serviceId: service.id,
            description: service.name,
            price: Number(service.price),
            quantity: 1,
            staffProfileId: null,
            manual: false,
        });
        renderLines();
        itemSelect.focus();
        setFeedback('', false);
    }

    function addManualLine() {
        lines.push({
            id: lineSeq++,
            serviceId: null,
            description: '',
            price: 0,
            quantity: 1,
            staffProfileId: null,
            manual: true,
        });
        renderLines();
    }

    addManualBtn.addEventListener('click', addManualLine);

    async function loadEligibleStaff(line, select) {
        if (!line.serviceId) {
            select.innerHTML = '<option value="">No staff assigned</option>';
            return;
        }

        select.innerHTML = '<option value="">Loading&hellip;</option>';

        const response = await fetch('{{ url("/services") }}/' + encodeURIComponent(line.serviceId) + '/eligible-staff', {
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
            row.style.gridTemplateColumns = '1.6fr 1.2fr 70px 110px auto';

            const descriptionCell = line.manual
                ? `<input type="text" class="sfp-input bill-line-description" placeholder="Description" value="${line.description}" style="margin-bottom:0;font-size:13.5px;padding:4px 8px">`
                : `<span style="font-size:14px">${line.description}</span>`;

            const priceCell = line.manual
                ? `<input type="number" step="0.01" min="0" class="sfp-input bill-line-price" value="${line.price}" style="margin-bottom:0;font-size:13.5px;padding:4px 8px;text-align:right">`
                : `<span class="sfp-mono" style="text-align:right;font-size:13.5px">${money(line.price)}</span>`;

            row.innerHTML = `
                <span class="bill-line-description-wrap">${descriptionCell}</span>
                <span></span>
                <input type="number" min="1" value="${line.quantity}" class="sfp-input bill-line-qty" style="margin-bottom:0;font-size:13.5px;padding:4px 8px">
                <span class="bill-line-price-wrap">${priceCell}</span>
                <button type="button" class="sfp-btn-outline bill-line-remove" style="padding:4px 10px">Remove</button>
            `;

            const staffSelect = document.createElement('select');
            staffSelect.className = 'sfp-select bill-line-staff';
            staffSelect.style.cssText = 'margin-bottom:0;font-size:13px;padding:4px 8px';
            staffSelect.addEventListener('change', () => {
                line.staffProfileId = staffSelect.value || null;
            });
            row.children[1].replaceWith(staffSelect);
            loadEligibleStaff(line, staffSelect);

            if (line.manual) {
                row.querySelector('.bill-line-description').addEventListener('input', (event) => {
                    line.description = event.target.value;
                });
                row.querySelector('.bill-line-price').addEventListener('input', (event) => {
                    line.price = Number(event.target.value) || 0;
                    updateTotal();
                });
            }

            row.querySelector('.bill-line-qty').addEventListener('input', (event) => {
                line.quantity = Math.max(1, parseInt(event.target.value, 10) || 1);
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

    function updateTotal() {
        const total = lines.reduce((sum, line) => sum + (Number(line.price) * Number(line.quantity)), 0);
        totalEl.textContent = money(total);
    }

    // ----- Payment method -----

    function selectMethod(method) {
        paymentMethod = method;
        methodButtons.forEach((btn) => btn.classList.toggle('active', btn.dataset.method === method));
    }

    methodButtons.forEach((btn) => {
        btn.addEventListener('click', () => selectMethod(btn.dataset.method));
    });

    document.addEventListener('keydown', (event) => {
        if (paymentSection.hidden) {
            return;
        }

        const active = document.activeElement;
        const inFormField = active?.closest('.ts-wrapper') || active?.classList?.contains('bill-line-description') || active?.classList?.contains('bill-line-price') || active?.classList?.contains('bill-line-qty');

        if (!inFormField && (event.key === '1' || event.key === '2' || event.key === '3')) {
            const map = { '1': 'cash', '2': 'card', '3': 'upi' };
            selectMethod(map[event.key]);
        }
    });

    // ----- Submission -----

    function buildItemsPayload() {
        return lines.map((line) => ({
            service_id: line.serviceId,
            staff_profile_id: line.staffProfileId || null,
            description: line.description,
            quantity: line.quantity,
            unit_price: line.price,
        }));
    }

    function validateLines() {
        if (lines.length === 0) {
            setFeedback('Add at least one item before creating the bill.', true);
            return false;
        }

        const missingDescription = lines.some((line) => line.manual && line.description.trim() === '');
        if (missingDescription) {
            setFeedback('Every manual item needs a description.', true);
            return false;
        }

        return true;
    }

    async function createBill() {
        if (!validateLines()) {
            return;
        }

        createBtn.disabled = true;
        setFeedback('Creating bill…', false);

        try {
            const response = await fetch('{{ route("bills.storeManual") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({
                    client_id: clientSelect.getValue() || null,
                    items: buildItemsPayload(),
                }),
            });

            if (response.redirected) {
                window.location.href = response.url;
                return;
            }

            const data = await response.json().catch(() => ({}));

            if (!response.ok) {
                setFeedback(data.message || 'Could not create the bill.', true);
                createBtn.disabled = false;
                return;
            }

            window.location.href = data.redirect || '{{ $tenantUrl->route("bills.index") }}';
        } catch (error) {
            setFeedback('Network error creating the bill.', true);
            createBtn.disabled = false;
        }
    }

    async function createAndSettle() {
        if (paymentSection.hidden) {
            paymentSection.hidden = false;
            return;
        }

        if (!validateLines()) {
            return;
        }

        settleBtn.disabled = true;
        setFeedback('Settling…', false);

        try {
            const response = await fetch('{{ route("bills.settle") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({
                    client_id: clientSelect.getValue() || null,
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

    createBtn.addEventListener('click', createBill);
    settleBtn.addEventListener('click', createAndSettle);

    renderLines();
})();
</script>
@endsection
