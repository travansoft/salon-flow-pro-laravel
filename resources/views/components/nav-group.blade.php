@props(['label', 'active' => false])

<details class="sfp-nav-group" @if($active) open @endif>
    <summary class="sfp-nav-item sfp-nav-group-toggle {{ $active ? 'active' : '' }}">
        <span class="sfp-nav-bar"></span>{{ $label }}
        <span class="sfp-nav-caret"></span>
    </summary>
    <div class="sfp-nav-children">
        {{ $slot }}
    </div>
</details>
