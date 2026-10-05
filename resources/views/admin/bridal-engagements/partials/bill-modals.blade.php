@can('billing.create')
    @php
        $staffOptions = $staffProfiles->map(fn ($staff) => ['id' => $staff->id, 'name' => $staff->name])->values();
        $oldStaff = old('staff', []);
        $remainingToBill = (float) $summary['total'] - (float) $summary['billed'];
        $createBillHasErrors = $errors->hasAny(['bill_date', 'amount', 'payment_method', 'staff']) || collect($errors->keys())->contains(fn ($key) => str_starts_with($key, 'staff.'));
    @endphp

    <div class="modal fade" id="createEventBillModal" tabindex="-1" aria-labelledby="createEventBillModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="{{ $tenantUrl->route('bridalEngagements.bills.store', $engagement) }}" method="POST">
                    @csrf

                    <div class="modal-header">
                        <h5 class="modal-title" id="createEventBillModalLabel">Create bill for event</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <div class="modal-body">
                        <div class="sfp-split-2">
                            <div class="sfp-field">
                                <label class="sfp-label">Date of bill</label>
                                <input type="date" name="bill_date" class="sfp-input" max="{{ now()->toDateString() }}" value="{{ old('bill_date', now()->toDateString()) }}">
                                @error('bill_date')
                                    <span class="sfp-invalid-feedback">{{ $message }}</span>
                                @enderror
                            </div>

                            <div class="sfp-field">
                                <label class="sfp-label">Amount to bill</label>
                                <input type="number" step="0.01" min="0.01" name="amount" class="sfp-input" value="{{ old('amount', $remainingToBill > 0 ? number_format($remainingToBill, 2, '.', '') : '') }}">
                                @error('amount')
                                    <span class="sfp-invalid-feedback">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>

                        <div class="sfp-field">
                            <label class="sfp-label">Payment mode</label>
                            <select name="payment_method" class="sfp-select">
                                <option value="cash" @selected(old('payment_method') === 'cash')>Cash</option>
                                <option value="upi" @selected(old('payment_method') === 'upi')>UPI</option>
                                <option value="card" @selected(old('payment_method') === 'card')>Card</option>
                            </select>
                            @error('payment_method')
                                <span class="sfp-invalid-feedback">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="sfp-field">
                            <label class="sfp-label">Servicing staff and target split (optional)</label>
                            <div id="event-bill-staff-rows" style="display:grid;gap:8px"></div>
                            <button type="button" id="event-bill-add-staff" class="sfp-btn-outline" style="margin-top:8px">+ Add staff</button>
                            <div style="font-size:12px;color:#66736F;margin-top:6px">Each amount is credited to that staff member's target. The split need not equal the bill amount. Leave empty if no staff assisted.</div>
                            @error('staff')
                                <span class="sfp-invalid-feedback">{{ $message }}</span>
                            @enderror
                            @foreach ($errors->get('staff.*') as $messages)
                                <span class="sfp-invalid-feedback">{{ $messages[0] }}</span>
                            @endforeach
                            @error('bill')
                                <span class="sfp-invalid-feedback">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="sfp-btn-outline" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="sfp-btn-primary">Create paid bill</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="attachEventBillModal" tabindex="-1" aria-labelledby="attachEventBillModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="{{ $tenantUrl->route('bridalEngagements.bills.attach', $engagement) }}" method="POST" id="event-bill-attach-form">
                    @csrf
                    <input type="hidden" name="bill_id" id="event-bill-attach-id">

                    <div class="modal-header">
                        <h5 class="modal-title" id="attachEventBillModalLabel">Attach existing bill</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <div class="modal-body">
                        <div class="sfp-field">
                            <label class="sfp-label">Bill number</label>
                            <div style="display:flex;gap:8px">
                                <input type="text" id="event-bill-number" class="sfp-input" placeholder="e.g. 42 or INV/2026-27/00042" autocomplete="off">
                                <button type="button" id="event-bill-next" class="sfp-btn-outline">Next</button>
                            </div>
                            <span class="sfp-invalid-feedback" id="event-bill-lookup-error" style="display:none"></span>
                        </div>

                        <div id="event-bill-info" style="display:none;padding:14px;border-radius:13px;background:#F3F6F5;border:1px solid #F0E7E1;font-size:13.5px;line-height:1.7"></div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="sfp-btn-outline" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="sfp-btn-primary" id="event-bill-attach-submit" disabled>Attach</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const staffOptions = @json($staffOptions);
        const initialRows = @json(array_values($oldStaff));
        const rowsBox = document.getElementById('event-bill-staff-rows');

        const addStaffRow = (row = {}) => {
            const wrapper = document.createElement('div');
            wrapper.style.cssText = 'display:grid;grid-template-columns:1fr 130px auto;gap:8px;align-items:center';

            const select = document.createElement('select');
            select.className = 'sfp-select';
            select.innerHTML = '<option value="">Select staff</option>' + staffOptions
                .map((staff) => `<option value="${staff.id}">${staff.name.replace(/</g, '&lt;')}</option>`).join('');
            select.value = row.staff_profile_id || '';

            const amount = document.createElement('input');
            amount.type = 'number';
            amount.step = '0.01';
            amount.min = '0.01';
            amount.className = 'sfp-input';
            amount.placeholder = 'Amount';
            amount.value = row.amount || '';

            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'sfp-btn-outline';
            remove.textContent = '×';
            remove.addEventListener('click', () => {
                wrapper.remove();
                renumber();
            });

            wrapper.append(select, amount, remove);
            rowsBox.appendChild(wrapper);
            renumber();
        };

        const renumber = () => {
            [...rowsBox.children].forEach((wrapper, index) => {
                wrapper.children[0].name = `staff[${index}][staff_profile_id]`;
                wrapper.children[1].name = `staff[${index}][amount]`;
            });
        };

        initialRows.forEach(addStaffRow);
        document.getElementById('event-bill-add-staff').addEventListener('click', () => addStaffRow());

        @if ($createBillHasErrors)
            bootstrap.Modal.getOrCreateInstance(document.getElementById('createEventBillModal')).show();
        @endif

        const numberInput = document.getElementById('event-bill-number');
        const nextButton = document.getElementById('event-bill-next');
        const info = document.getElementById('event-bill-info');
        const lookupError = document.getElementById('event-bill-lookup-error');
        const billIdInput = document.getElementById('event-bill-attach-id');
        const attachSubmit = document.getElementById('event-bill-attach-submit');

        const resetLookup = () => {
            info.style.display = 'none';
            lookupError.style.display = 'none';
            billIdInput.value = '';
            attachSubmit.disabled = true;
        };

        const lookup = async () => {
            resetLookup();
            const number = numberInput.value.trim();

            if (number === '') {
                return;
            }

            const response = await fetch('{{ $tenantUrl->route("bridalEngagements.bills.lookup", $engagement) }}?number=' + encodeURIComponent(number), {
                headers: { 'Accept': 'application/json' },
            });
            const data = await response.json();

            if (!response.ok) {
                lookupError.textContent = data.message || 'Could not look up that bill.';
                lookupError.style.display = 'block';
                return;
            }

            const bill = data.bill;
            info.innerHTML = '';

            [
                ['Bill', bill.invoice_number],
                ['Client', bill.client || '—'],
                ['Date', bill.date],
                ['Total', bill.total],
                ['Paid', bill.paid],
                ['Status', bill.status],
            ].forEach(([label, value]) => {
                const line = document.createElement('div');
                const strong = document.createElement('strong');
                strong.textContent = label + ': ';
                line.append(strong, document.createTextNode(value));
                info.appendChild(line);
            });

            info.style.display = 'block';

            if (bill.unavailable_reason) {
                lookupError.textContent = bill.unavailable_reason;
                lookupError.style.display = 'block';
                return;
            }

            billIdInput.value = bill.id;
            attachSubmit.disabled = false;
        };

        nextButton.addEventListener('click', lookup);
        numberInput.addEventListener('input', resetLookup);
        numberInput.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
                lookup();
            }
        });
    });
    </script>
@endcan
