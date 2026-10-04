@props(['bill', 'showLabel' => false])

@if ($bill->hasMissingServiceStaff())
    <span title="Servicing staff is not set for every service in this bill" aria-label="Servicing staff is not set for every service in this bill" style="color:#C98A2C;white-space:nowrap;display:inline-flex;align-items:center;gap:5px">
        <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M8.982 1.566a1.13 1.13 0 0 0-1.96 0L.165 13.233c-.457.778.091 1.767.98 1.767h13.713c.889 0 1.438-.99.98-1.767zM8 5c.535 0 .954.462.9.995l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 5.995A.905.905 0 0 1 8 5m.002 6a1 1 0 1 1 0 2 1 1 0 0 1 0-2"/></svg>
        @if ($showLabel)
            <span style="font-size:12.5px">Servicing staff not set for all services</span>
        @endif
    </span>
@endif
