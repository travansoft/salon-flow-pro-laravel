@props(['tenantUrl'])

<div class="sfp-user-menu" data-user-menu>
    <button type="button" class="sfp-avatar-btn" title="Account" aria-haspopup="true" aria-expanded="false" data-user-menu-toggle>{{ strtoupper(substr(auth()->user()->name ?? 'U', 0, 1)) }}</button>
    <div class="sfp-user-menu-panel" hidden>
        <div class="sfp-user-menu-head">
            <div class="sfp-user-menu-name">{{ auth()->user()->name }}</div>
            <div class="sfp-user-menu-role">{{ auth()->user()->getRoleNames()->first() ?? '—' }}</div>
        </div>
        <a href="{{ $tenantUrl->route('profile.show') }}" class="sfp-user-menu-item">My profile</a>
        <a href="{{ $tenantUrl->route('password.edit') }}" class="sfp-user-menu-item">Change password</a>
        <form action="{{ $tenantUrl->route('logout') }}" method="POST">
            @csrf
            <button type="submit" class="sfp-user-menu-item">Logout</button>
        </form>
    </div>
</div>
