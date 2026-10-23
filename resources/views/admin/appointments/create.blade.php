@extends('layouts.admin')

@section('title', 'New Appointment')

@section('content')
    <div class="sfp-page-header">
        <div>
            <h1 class="sfp-page-title">New appointment</h1>
            <div class="sfp-page-subtitle">Book a client in for a service</div>
        </div>
    </div>

    <div class="sfp-card">
        <form action="{{ $tenantUrl->route('appointments.store') }}" method="POST" id="appt-form">
            @csrf

            <div class="sfp-field">
                <label class="sfp-label">Mobile number</label>
                <input type="text" name="client_phone" id="appt-client-phone" class="sfp-input" autocomplete="off"
                       placeholder="Type mobile number" value="{{ old('client_phone') }}">
                <input type="hidden" name="client_id" id="appt-client-id" value="{{ old('client_id') }}">
                <div id="appt-client-results" class="sfp-autosuggest-list" style="display:none"></div>
                @error('client_phone')
                    <span class="sfp-invalid-feedback">{{ $message }}</span>
                @enderror
                @error('client_id')
                    <span class="sfp-invalid-feedback">{{ $message }}</span>
                @enderror
            </div>

            <div class="sfp-field">
                <label class="sfp-label">Client name</label>
                <input type="text" name="client_name" id="appt-client-name" class="sfp-input" autocomplete="off"
                       placeholder="Type client name" value="{{ old('client_name') }}">
                @error('client_name')
                    <span class="sfp-invalid-feedback">{{ $message }}</span>
                @enderror
            </div>

            <div class="sfp-field">
                <label class="sfp-label">Services</label>
                <input type="text" id="appt-service-search" class="sfp-input" autocomplete="off"
                       placeholder="Search services by name&hellip;">
                <div id="appt-service-results" class="sfp-autosuggest-list" style="display:none"></div>

                <div class="sfp-table-wrap" id="appt-lines-wrap" style="display:none;margin-top:12px">
                    <div class="sfp-table-head-row" style="grid-template-columns:1.4fr 1fr 90px">
                        <span>Service</span>
                        <span>Staff</span>
                        <span></span>
                    </div>
                    <div id="appt-lines"></div>
                </div>

                @error('services')
                    <span class="sfp-invalid-feedback">{{ $message }}</span>
                @enderror
            </div>

            <div class="sfp-field">
                <label class="sfp-label">Date</label>
                <input type="date" id="appt-date" class="sfp-input" value="{{ old('appt_date', now()->toDateString()) }}">
            </div>

            <div class="sfp-field">
                <label class="sfp-label">Time</label>
                <div style="display:flex;gap:12px;align-items:center">
                    <input type="time" id="appt-from-time" class="sfp-input" style="margin-bottom:0" value="{{ old('start_at') ? substr(old('start_at'), 11, 5) : '' }}" aria-label="From">
                    <span style="color:#66736F">to</span>
                    <input type="time" id="appt-to-time" class="sfp-input" style="margin-bottom:0" value="{{ old('end_at') ? substr(old('end_at'), 11, 5) : '' }}" aria-label="To">
                </div>
                <input type="hidden" name="start_at" id="appt-start-at" value="{{ old('start_at') }}">
                <input type="hidden" name="end_at" id="appt-end-at" value="{{ old('end_at') }}">
                @error('start_at')
                    <span class="sfp-invalid-feedback">{{ $message }}</span>
                @enderror
                @error('end_at')
                    <span class="sfp-invalid-feedback">{{ $message }}</span>
                @enderror
            </div>

            <div class="sfp-field">
                <label class="sfp-label">Notes</label>
                <textarea name="notes" class="sfp-textarea">{{ old('notes') }}</textarea>
                @error('notes')
                    <span class="sfp-invalid-feedback">{{ $message }}</span>
                @enderror
            </div>

            <div class="sfp-form-actions">
                <button type="submit" class="sfp-btn-primary">Book</button>
            </div>
        </form>
    </div>
@endsection

@section('scripts')
<script>
(function () {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
    const phoneInput = document.getElementById('appt-client-phone');
    const nameInput = document.getElementById('appt-client-name');
    const resultsBox = document.getElementById('appt-client-results');
    const clientIdInput = document.getElementById('appt-client-id');
    const dateInput = document.getElementById('appt-date');
    const fromTimeInput = document.getElementById('appt-from-time');
    const toTimeInput = document.getElementById('appt-to-time');
    const endAtInput = document.getElementById('appt-end-at');
    const startAtInput = document.getElementById('appt-start-at');
    const serviceSearchInput = document.getElementById('appt-service-search');
    const serviceResultsBox = document.getElementById('appt-service-results');
    const linesWrap = document.getElementById('appt-lines-wrap');
    const linesBody = document.getElementById('appt-lines');
    const apptForm = document.getElementById('appt-form');

    let clientSearchTimer = null;
    let serviceSearchTimer = null;
    let lines = [];

    function renderLines() {
        linesBody.innerHTML = '';
        linesWrap.style.display = lines.length ? 'block' : 'none';

        lines.forEach((line, index) => {
            const row = document.createElement('div');
            row.className = 'sfp-table-row';
            row.style.gridTemplateColumns = '1.4fr 1fr 90px';
            row.innerHTML = `
                <span style="font-size:14px">${line.name}
                    <span class="sfp-mono" style="color:#94A19D;font-size:12px">&mdash; ${line.duration} min</span>
                </span>
                <span class="appt-line-staff-slot"></span>
                <span class="appt-line-remove-slot"></span>
            `;

            const staffSelect = document.createElement('select');
            staffSelect.className = 'sfp-input appt-line-staff';
            staffSelect.style.cssText = 'margin-bottom:0;font-size:13px;padding:4px 8px';
            staffSelect.innerHTML = '<option value="">Loading&hellip;</option>';
            staffSelect.addEventListener('change', () => {
                line.staffProfileId = staffSelect.value || null;
            });

            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'sfp-btn-link-danger';
            removeBtn.textContent = 'Remove';
            removeBtn.addEventListener('click', () => {
                lines.splice(index, 1);
                renderLines();
            });

            row.querySelector('.appt-line-staff-slot').replaceWith(staffSelect);
            row.querySelector('.appt-line-remove-slot').replaceWith(removeBtn);
            linesBody.appendChild(row);

            loadEligibleStaff(line.serviceId, staffSelect, line.staffProfileId);
        });
    }

    async function loadEligibleStaff(serviceId, select, selectedId) {
        const response = await fetch('{{ $tenantUrl->route("appointments.staff") }}', {
            headers: { 'Accept': 'application/json' },
        });

        const data = response.ok ? await response.json() : { staff: [] };
        const staff = data.staff || [];

        select.innerHTML = '<option value="">Select staff&hellip;</option>' + staff.map((member) =>
            `<option value="${member.id}" ${String(member.id) === String(selectedId || '') ? 'selected' : ''}>${member.name}</option>`
        ).join('');
    }

    function addService(service) {
        const serviceId = String(service.id);

        if (lines.some((line) => line.serviceId === serviceId)) {
            serviceSearchInput.value = '';
            serviceResultsBox.style.display = 'none';
            return;
        }

        lines.push({
            serviceId,
            name: service.name,
            duration: service.duration_minutes,
            staffProfileId: null,
        });

        renderLines();
        serviceSearchInput.value = '';
        serviceResultsBox.style.display = 'none';
        serviceResultsBox.innerHTML = '';
    }

    function renderServiceResults(services) {
        serviceResultsBox.innerHTML = '';

        if (services.length === 0) {
            serviceResultsBox.style.display = 'none';
            return;
        }

        services.forEach((service) => {
            const alreadyAdded = lines.some((line) => line.serviceId === String(service.id));

            const row = document.createElement('div');
            row.className = 'sfp-autosuggest-item';
            row.style.opacity = alreadyAdded ? '0.5' : '1';
            row.innerHTML = `${service.name}
                <span class="sfp-mono" style="color:#94A19D;font-size:12px">&mdash; ${service.duration_minutes} min &middot; &#8377;${Number(service.price).toFixed(2)}</span>
                ${alreadyAdded ? ' <span style="font-size:12px">(added)</span>' : ''}`;

            if (!alreadyAdded) {
                row.addEventListener('click', () => addService(service));
            }

            serviceResultsBox.appendChild(row);
        });

        serviceResultsBox.style.display = 'block';
    }

    async function searchServices(term) {
        const response = await fetch('{{ $tenantUrl->route("appointments.searchServices") }}?q=' + encodeURIComponent(term), {
            headers: { 'Accept': 'application/json' },
        });

        if (!response.ok) {
            return;
        }

        const data = await response.json();
        renderServiceResults(data.services || []);
    }

    serviceSearchInput.addEventListener('input', () => {
        const term = serviceSearchInput.value.trim();

        clearTimeout(serviceSearchTimer);
        serviceSearchTimer = setTimeout(() => searchServices(term), 250);
    });

    serviceSearchInput.addEventListener('focus', () => {
        if (serviceSearchInput.value.trim() === '') {
            searchServices('');
        }
    });

    document.addEventListener('click', (event) => {
        if (!serviceResultsBox.contains(event.target) && event.target !== serviceSearchInput) {
            serviceResultsBox.style.display = 'none';
        }
    });

    apptForm.addEventListener('submit', (event) => {
        document.querySelectorAll('.appt-line-hidden-input').forEach((el) => el.remove());

        if (lines.some((line) => !line.staffProfileId)) {
            event.preventDefault();
            alert('Select a staff member for every service.');
            return;
        }

        lines.forEach((line, index) => {
            const serviceInput = document.createElement('input');
            serviceInput.type = 'hidden';
            serviceInput.className = 'appt-line-hidden-input';
            serviceInput.name = `services[${index}][service_id]`;
            serviceInput.value = line.serviceId;
            apptForm.appendChild(serviceInput);

            const staffInput = document.createElement('input');
            staffInput.type = 'hidden';
            staffInput.className = 'appt-line-hidden-input';
            staffInput.name = `services[${index}][staff_profile_id]`;
            staffInput.value = line.staffProfileId;
            apptForm.appendChild(staffInput);
        });
    });

    function selectClient(client) {
        clientIdInput.value = client.id;
        phoneInput.value = client.phone || '';
        nameInput.value = client.name;
        resultsBox.style.display = 'none';
    }

    function renderResults(clients) {
        resultsBox.innerHTML = '';

        if (clients.length === 0) {
            resultsBox.style.display = 'none';
            return;
        }

        clients.forEach((client) => {
            const row = document.createElement('div');
            row.className = 'sfp-autosuggest-item';
            row.textContent = (client.phone ? client.phone + ' · ' : '') + client.name;
            row.addEventListener('click', () => selectClient(client));
            resultsBox.appendChild(row);
        });
        resultsBox.style.display = 'block';
    }

    async function search(term) {
        const response = await fetch('{{ $tenantUrl->route("appointments.searchClients") }}?q=' + encodeURIComponent(term), {
            headers: { 'Accept': 'application/json' },
        });

        if (!response.ok) {
            return;
        }

        const data = await response.json();
        const clients = data.clients || [];
        const exact = clients.find((client) => client.phone === term);

        if (exact) {
            selectClient(exact);
            return;
        }

        renderResults(clients);
    }

    phoneInput.addEventListener('input', () => {
        clientIdInput.value = '';
        const term = phoneInput.value.trim();

        clearTimeout(clientSearchTimer);

        if (term.length < 3) {
            resultsBox.style.display = 'none';
            return;
        }

        clientSearchTimer = setTimeout(() => search(term), 250);
    });

    function updateStartAt() {
        startAtInput.value = dateInput.value && fromTimeInput.value ? dateInput.value + 'T' + fromTimeInput.value : '';
        endAtInput.value = dateInput.value && toTimeInput.value ? dateInput.value + 'T' + toTimeInput.value : '';
    }

    dateInput.addEventListener('change', updateStartAt);
    fromTimeInput.addEventListener('input', updateStartAt);
    toTimeInput.addEventListener('input', updateStartAt);
    updateStartAt();

    document.addEventListener('click', (event) => {
        if (!resultsBox.contains(event.target) && event.target !== phoneInput) {
            resultsBox.style.display = 'none';
        }
    });
})();
</script>
@endsection
