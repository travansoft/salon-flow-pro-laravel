@php
    $engagement = $engagement ?? null;
    $value = fn (string $field, $default = null) => old($field, $engagement?->{$field} ?? $default);
    $venueType = old('venue_type', $engagement?->venue_type?->value ?? 'studio');
    $dressType = old('dress_type', $engagement?->dress_type?->value ?? 'saree');
    $hasTrial = (bool) old('has_studio_trial', $engagement?->has_studio_trial ?? false);
    $groomMakeup = (bool) old('groom_makeup', $engagement?->groom_makeup ?? false);
@endphp

<div class="sfp-split-2">
    <div class="sfp-field" style="position:relative">
        <label class="sfp-label">Contact number</label>
        <input type="text" name="contact_number" id="be-contact" class="sfp-input" autocomplete="off" value="{{ old('contact_number', $engagement?->client?->phone) }}">
        <div id="be-client-results" class="sfp-autosuggest-list" style="display:none"></div>
        <input type="hidden" name="client_id" id="be-client-id" value="{{ old('client_id', $engagement?->client_id) }}">
        @error('contact_number')
            <span class="sfp-invalid-feedback">{{ $message }}</span>
        @enderror
    </div>

    <div class="sfp-field">
        <label class="sfp-label">Bride name</label>
        <input type="text" name="bride_name" id="be-bride-name" class="sfp-input" value="{{ old('bride_name', $engagement?->client?->name) }}">
        @error('bride_name')
            <span class="sfp-invalid-feedback">{{ $message }}</span>
        @enderror
    </div>
</div>

<div class="sfp-split-2">
    <div class="sfp-field">
        <label class="sfp-label">Event name</label>
        <input type="text" name="event_name" class="sfp-input" value="{{ $value('event_name') }}">
        @error('event_name')
            <span class="sfp-invalid-feedback">{{ $message }}</span>
        @enderror
    </div>

    <div class="sfp-field">
        <label class="sfp-label">Date of event</label>
        <input type="date" name="event_date" class="sfp-input" value="{{ old('event_date', $engagement?->event_date?->toDateString()) }}">
        @error('event_date')
            <span class="sfp-invalid-feedback">{{ $message }}</span>
        @enderror
    </div>
</div>

<div class="sfp-split-2">
    <div class="sfp-field">
        <label class="sfp-label">Venue</label>
        <select name="venue_type" id="be-venue-type" class="sfp-select">
            <option value="studio" @selected($venueType === 'studio')>Studio</option>
            <option value="home" @selected($venueType === 'home')>Home</option>
        </select>
        @error('venue_type')
            <span class="sfp-invalid-feedback">{{ $message }}</span>
        @enderror
    </div>

    <div class="sfp-field" id="be-home-location-field">
        <label class="sfp-label">Home location</label>
        <input type="text" name="home_location" class="sfp-input" value="{{ $value('home_location') }}">
        @error('home_location')
            <span class="sfp-invalid-feedback">{{ $message }}</span>
        @enderror
    </div>
</div>

<div class="sfp-split-2">
    <div class="sfp-field">
        <label class="sfp-label">Studio trial</label>
        <select name="has_studio_trial" id="be-has-trial" class="sfp-select">
            <option value="0" @selected(! $hasTrial)>No</option>
            <option value="1" @selected($hasTrial)>Yes</option>
        </select>
        @error('has_studio_trial')
            <span class="sfp-invalid-feedback">{{ $message }}</span>
        @enderror
    </div>

    <div class="sfp-field" id="be-trial-date-field">
        <label class="sfp-label">Date of trial</label>
        <input type="date" name="trial_date" class="sfp-input" value="{{ old('trial_date', $engagement?->trial_date?->toDateString()) }}">
        @error('trial_date')
            <span class="sfp-invalid-feedback">{{ $message }}</span>
        @enderror
    </div>
</div>

<div class="sfp-field">
    <label class="sfp-label">Time to get ready</label>
    <input type="time" name="ready_time" class="sfp-input" value="{{ old('ready_time', $engagement?->ready_time ? \Illuminate\Support\Str::substr($engagement->ready_time, 0, 5) : null) }}">
    @error('ready_time')
        <span class="sfp-invalid-feedback">{{ $message }}</span>
    @enderror
</div>

<div class="sfp-split-2">
    <div class="sfp-field">
        <label class="sfp-label">Total amount</label>
        <input type="number" step="0.01" min="0" name="total_amount" class="sfp-input" value="{{ $value('total_amount') }}">
        @error('total_amount')
            <span class="sfp-invalid-feedback">{{ $message }}</span>
        @enderror
    </div>

    <div class="sfp-field">
        <label class="sfp-label">Advance amount</label>
        <input type="number" step="0.01" min="0" name="advance_amount" class="sfp-input" value="{{ $value('advance_amount') }}">
        @error('advance_amount')
            <span class="sfp-invalid-feedback">{{ $message }}</span>
        @enderror
    </div>
</div>

<div class="sfp-split-2">
    <div class="sfp-field">
        <label class="sfp-label">Number of guest makeup (optional)</label>
        <input type="number" min="0" name="guest_makeup_count" class="sfp-input" value="{{ $value('guest_makeup_count') }}">
        @error('guest_makeup_count')
            <span class="sfp-invalid-feedback">{{ $message }}</span>
        @enderror
    </div>

    <div class="sfp-field">
        <label class="sfp-label">Groom makeup (optional)</label>
        <select name="groom_makeup" class="sfp-select">
            <option value="0" @selected(! $groomMakeup)>No</option>
            <option value="1" @selected($groomMakeup)>Yes</option>
        </select>
        @error('groom_makeup')
            <span class="sfp-invalid-feedback">{{ $message }}</span>
        @enderror
    </div>
</div>

<div class="sfp-split-2">
    <div class="sfp-field">
        <label class="sfp-label">Dress</label>
        <select name="dress_type" id="be-dress-type" class="sfp-select">
            <option value="saree" @selected($dressType === 'saree')>Saree</option>
            <option value="others" @selected($dressType === 'others')>Others</option>
        </select>
        @error('dress_type')
            <span class="sfp-invalid-feedback">{{ $message }}</span>
        @enderror
    </div>

    <div class="sfp-field" id="be-drapist-field">
        <label class="sfp-label">Name of saree drapist</label>
        <input type="text" name="saree_drapist_name" class="sfp-input" value="{{ $value('saree_drapist_name') }}">
        @error('saree_drapist_name')
            <span class="sfp-invalid-feedback">{{ $message }}</span>
        @enderror
    </div>
</div>

<div class="sfp-field">
    <label class="sfp-label">Note</label>
    <textarea name="notes" class="sfp-textarea">{{ $value('notes') }}</textarea>
    @error('notes')
        <span class="sfp-invalid-feedback">{{ $message }}</span>
    @enderror
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const toggle = (selectId, fieldId, shownWhen) => {
        const select = document.getElementById(selectId);
        const field = document.getElementById(fieldId);
        const apply = () => { field.style.display = select.value === shownWhen ? '' : 'none'; };
        select.addEventListener('change', apply);
        apply();
    };

    toggle('be-venue-type', 'be-home-location-field', 'home');
    toggle('be-has-trial', 'be-trial-date-field', '1');
    toggle('be-dress-type', 'be-drapist-field', 'saree');

    const contact = document.getElementById('be-contact');
    const results = document.getElementById('be-client-results');
    const clientId = document.getElementById('be-client-id');
    const brideName = document.getElementById('be-bride-name');
    let timer = null;

    contact.addEventListener('input', () => {
        clientId.value = '';
        clearTimeout(timer);
        const term = contact.value.trim();

        if (term.length < 3) {
            results.style.display = 'none';
            return;
        }

        timer = setTimeout(async () => {
            const response = await fetch('{{ $tenantUrl->route("appointments.searchClients") }}?q=' + encodeURIComponent(term), {
                headers: { 'Accept': 'application/json' },
            });

            if (!response.ok) {
                return;
            }

            const data = await response.json();
            results.innerHTML = '';

            (data.clients || []).forEach((client) => {
                const row = document.createElement('div');
                row.className = 'sfp-autosuggest-item';
                row.textContent = client.name + (client.phone ? ' · ' + client.phone : '');
                row.addEventListener('click', () => {
                    clientId.value = client.id;
                    contact.value = client.phone || '';
                    brideName.value = client.name;
                    results.style.display = 'none';
                });
                results.appendChild(row);
            });

            results.style.display = results.children.length ? 'block' : 'none';
        }, 250);
    });
});
</script>
