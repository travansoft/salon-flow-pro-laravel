<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Super Admin')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Bricolage+Grotesque:opsz,wght@12..96,400..800&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('admin/css/styles.css') }}">
    @yield('styles')
</head>
<body class="sfp-body">
    <div class="sfp-shell">
        <div class="sfp-frame">

            <div class="sfp-topbar">
                <div class="sfp-brand">
                    <div class="sfp-brand-mark">S</div>
                    <div class="sfp-brand-text">
                        <div class="sfp-brand-name">SalonFlow Pro — Platform</div>
                    </div>
                </div>
                <div class="sfp-topbar-actions">
                    <form action="{{ $superAdminUrl->route('superAdmin.logout') }}" method="POST">
                        @csrf
                        <button type="submit" class="sfp-avatar-btn" title="Sign out">{{ strtoupper(substr(auth('super_admin')->user()->name ?? 'A', 0, 1)) }}</button>
                    </form>
                </div>
            </div>

            <div class="sfp-body-row">
                <nav class="sfp-sidebar">
                    <div class="sfp-sidebar-label">PLATFORM</div>

                    <a href="{{ $superAdminUrl->route('superAdmin.dashboard') }}" class="sfp-nav-item {{ request()->routeIs('superAdmin.dashboard*') ? 'active' : '' }}">
                        <span class="sfp-nav-bar"></span>Dashboard
                    </a>

                    <a href="{{ $superAdminUrl->route('superAdmin.tenants.index') }}" class="sfp-nav-item {{ request()->routeIs('superAdmin.tenants.*') ? 'active' : '' }}">
                        <span class="sfp-nav-bar"></span>Tenants
                    </a>

                    <a href="{{ $superAdminUrl->route('superAdmin.platformAdmins.index') }}" class="sfp-nav-item {{ request()->routeIs('superAdmin.platformAdmins.*') ? 'active' : '' }}">
                        <span class="sfp-nav-bar"></span>Admin users
                    </a>

                    <a href="{{ $superAdminUrl->route('superAdmin.activity.index') }}" class="sfp-nav-item {{ request()->routeIs('superAdmin.activity.*') ? 'active' : '' }}">
                        <span class="sfp-nav-bar"></span>Activity log
                    </a>

                    <a href="{{ $superAdminUrl->route('superAdmin.profile.edit') }}" class="sfp-nav-item {{ request()->routeIs('superAdmin.profile.*') ? 'active' : '' }}">
                        <span class="sfp-nav-bar"></span>My profile
                    </a>

                    <div class="sfp-sidebar-footer">
                        Signed in as <strong>{{ auth('super_admin')->user()->name ?? '—' }}</strong>
                    </div>
                </nav>

                <main class="sfp-content">
                    @if (session('status'))
                        <div class="sfp-alert-success">{{ session('status') }}</div>
                    @endif

                    @yield('content')
                </main>
            </div>

        </div>
    </div>

    <script src="{{ asset('admin/js/scripts.js') }}"></script>
    @yield('scripts')
</body>
</html>
