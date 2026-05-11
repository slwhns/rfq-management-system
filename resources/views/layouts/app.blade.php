<!DOCTYPE html>
<html lang="en">
<head>
    <title>@yield('title', 'RFQ Management System')</title>

    <!-- Favicon -->
    <link rel="icon" href="{{ asset('images/system-logo.svg') }}" type="image/svg+xml">

    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- Custom CSS -->
    <link href="https://fonts.googleapis.com/css2?family=Jura:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.2.0/fonts/remixicon.css" rel="stylesheet">

    <link rel="stylesheet" href="{{ asset('css/custom-overrides.css') }}">
    <link rel="stylesheet" href="{{ asset('css/layout.css') }}">
    <link rel="stylesheet" href="{{ asset('css/modals.css') }}">
    <link rel="stylesheet" href="{{ asset('css/profile-page.css') }}">
</head>

<body class="bg-white3 app-theme">

@php
    $currentUser = auth()->user();
    $displayName = $currentUser?->name ?? 'User';
    $displayRole = ucfirst($currentUser?->normalizedRole() ?? 'client');
    $avatarInitial = strtoupper(substr($displayName, 0, 1));
    $avatarUrl = $currentUser?->profile_photo_path ? \Illuminate\Support\Facades\Storage::url($currentUser->profile_photo_path) : null;
@endphp

<div class="d-flex app-shell">

    <!-- Sidebar -->
    <div class="app-sidebar">
        <div class="app-sidebar-inner">

        <div class="app-brand-wrap mg-b-10">
            <div class="app-brand">
                <img src="{{ asset('images/system-logo.svg') }}" alt="RFQ Management System Logo" class="app-brand-logo">
            </div>
        </div>

        <div class="app-nav-group-label">
            <span class="app-nav-group-label-short">Main</span>
            <span class="app-nav-group-label-full">Main</span>
        </div>

        <button type="button" data-route="dashboard" class="nav-btn pd-10 br-5 mg-b-10 cursor-pointer {{ request()->is('dashboard') ? 'app-nav-active' : '' }}"
            onclick="custom_nav_click(this, 'nav-btn', 'app-nav-active', '/dashboard')">
            <span class="app-nav-icon"><i class="ri-home-2-line"></i></span>
            <span class="app-nav-text">
                <span class="app-nav-text-short">Home</span>
                <span class="app-nav-text-full">Dashboard</span>
            </span>
        </button>

        <div class="app-nav-group-label">
            <span class="app-nav-group-label-short">Man.</span>
            <span class="app-nav-group-label-full">Management</span>
        </div>

        <button type="button" data-route="rfqs" class="nav-btn pd-10 br-5 mg-b-10 cursor-pointer {{ request()->is('quotes*') || request()->is('rfqs*') ? 'app-nav-active' : '' }}"
            onclick="custom_nav_click(this, 'nav-btn', 'app-nav-active', '/rfqs')">
            <span class="app-nav-icon"><i class="ri-draft-line"></i></span>
            <span class="app-nav-text">
                <span class="app-nav-text-short">RFQ</span>
                <span class="app-nav-text-full">RFQ</span>
            </span>
        </button>

        @if(($currentUser?->normalizedRole() ?? 'client') === 'superadmin')
            <button type="button" data-route="admin/staff" class="nav-btn pd-10 br-5 mg-b-10 cursor-pointer {{ request()->is('admin/staff*') ? 'app-nav-active' : '' }}"
                onclick="custom_nav_click(this, 'nav-btn', 'app-nav-active', '/admin/staff')">
                <span class="app-nav-icon"><i class="ri-team-line"></i></span>
                <span class="app-nav-text">
                    <span class="app-nav-text-short">Stf.</span>
                    <span class="app-nav-text-full">Staff</span>
                </span>
            </button>
        @endif

        <button type="button" data-route="projects" class="nav-btn pd-10 br-5 mg-b-10 cursor-pointer {{ request()->is('projects*') || request()->is('pricing*') ? 'app-nav-active' : '' }}"
            onclick="custom_nav_click(this, 'nav-btn', 'app-nav-active', '/projects')">
            <span class="app-nav-icon">◎</span>
            <span class="app-nav-text">
                <span class="app-nav-text-short">Proj.</span>
                <span class="app-nav-text-full">Projects &amp; Pricing</span>
            </span>
        </button>

        @if(in_array(($currentUser?->normalizedRole() ?? 'client'), ['superadmin', 'admin'], true))
            <button type="button" data-route="suppliers" class="nav-btn pd-10 br-5 mg-b-10 cursor-pointer {{ request()->is('suppliers*') ? 'app-nav-active' : '' }}"
                onclick="custom_nav_click(this, 'nav-btn', 'app-nav-active', '/suppliers')">
                <span class="app-nav-icon">◈</span>
                <span class="app-nav-text">
                    <span class="app-nav-text-short">Supp.</span>
                    <span class="app-nav-text-full">Suppliers</span>
                </span>
            </button>
        @endif

        </div>

    </div>

    <!-- RIGHT SIDE -->
    <div class="fg-1 d-flex fd-column app-content">

        <!-- HEADER -->
        <div class="header d-flex jc-between ai-center pd-15 bg-white5 bdr-bottom-22 box-shadow-basic app-header">

            <div>
                <div class="fs-18 fw-bold header-title">RFQ Management System</div>
                <div class="fs-12 header-subtitle">Request For Quotation</div>
            </div>

            <button type="button" class="header-profile-trigger" id="header-profile-trigger" aria-label="Open profile panel">
                <div class="fs-13 header-user">{{ $displayName }}</div>
                <span class="header-profile-avatar">
                    @if($avatarUrl)
                        <img src="{{ $avatarUrl }}" alt="{{ $displayName }} avatar" class="header-profile-avatar-image">
                    @else
                        {{ $avatarInitial }}
                    @endif
                </span>
            </button>

        </div>

        <!-- Animated background orbs -->
        <div class="bg-orbs" aria-hidden="true">
            <div class="bg-orb bg-orb-1"></div>
            <div class="bg-orb bg-orb-2"></div>
            <div class="bg-orb bg-orb-3"></div>
        </div>

        <!-- MAIN CONTENT -->
        <div class="pd-20 app-main" id="main-content">
            @yield('content')
        </div>

    </div>

</div>

<div class="profile-rail-overlay" id="profile-rail-overlay" aria-hidden="true"></div>
<div class="profile-rail-hitarea" id="profile-rail-hitarea" aria-hidden="true"></div>

<aside class="profile-rail" id="profile-rail" aria-hidden="true">
    <div class="profile-rail-top">
        <span class="header-profile-avatar">
            @if($avatarUrl)
                <img src="{{ $avatarUrl }}" alt="{{ $displayName }} avatar" class="header-profile-avatar-image">
            @else
                {{ $avatarInitial }}
            @endif
        </span>
        <div class="profile-rail-actions">
            <a href="{{ route('profile.index') }}" class="profile-rail-btn" title="Profile" aria-label="Profile">
                <i class="ri-user-3-line"></i>
            </a>

            <button type="button" id="theme-toggle-btn" class="profile-rail-btn" aria-label="Toggle theme" title="Switch to Light Theme">
                <i class="ri-sun-line" id="theme-icon"></i>
            </button>

            <form method="POST" action="{{ route('logout') }}" data-no-spa="true">
                @csrf
                <button type="submit" class="profile-rail-btn profile-rail-logout" title="Logout" aria-label="Logout" data-no-spa="true">
                    <i class="ri-logout-box-r-line"></i>
                </button>
            </form>
        </div>
    </div>
</aside>

<script src="{{ asset('js/api-helper.js') }}"></script>
<script src="{{ asset('js/modals.js') }}"></script>
<script src="{{ asset('js/profile-page.js') }}"></script>
<script type="module" src="{{ asset('js/app.js') }}"></script>

@stack('scripts')

<script>
(function () {
    const STORAGE_KEY = 'qs_theme';
    const body = document.body;
    const btn = document.getElementById('theme-toggle-btn');
    const icon = document.getElementById('theme-icon');

    function applyTheme(theme) {
        if (theme === 'light') {
            body.classList.add('light-theme');
            if (icon) {
                icon.className = 'ri-moon-line';
                btn.title = 'Switch to Dark Theme';
            }
        } else {
            body.classList.remove('light-theme');
            if (icon) {
                icon.className = 'ri-sun-line';
                btn.title = 'Switch to Light Theme';
            }
        }
    }

    // Apply saved theme on load
    const saved = localStorage.getItem(STORAGE_KEY) || 'dark';
    applyTheme(saved);

    // Toggle on click
    if (btn) {
        btn.addEventListener('click', function () {
            const isLight = body.classList.contains('light-theme');
            const next = isLight ? 'dark' : 'light';
            localStorage.setItem(STORAGE_KEY, next);
            applyTheme(next);
        });
    }
})();
</script>

</body>
</html>

