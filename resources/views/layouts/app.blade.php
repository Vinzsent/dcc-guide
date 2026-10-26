<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="{{ $userTheme }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'DCC Care')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<div class="shell">

    <aside class="sidebar">
        <div class="brand">
            <div class="brand-mark">
                @if ($schoolLogo)
                    <img src="{{ asset($schoolLogo) }}" alt="DCC logo">
                @else
                    D
                @endif
            </div>
            <div>
                <div class="brand-name">DCC Care</div>
                <div class="brand-sub">Discipline Office</div>
            </div>
        </div>

        <div class="nav-section">Office</div>
        <nav class="nav">
            <a href="{{ route('dashboard') }}" @class(['active' => request()->routeIs('dashboard')])>
                <svg viewBox="0 0 16 16" fill="none"><rect x="1.5" y="1.5" width="5.4" height="5.4" rx="1" stroke="currentColor" stroke-width="1.3"/><rect x="9.1" y="1.5" width="5.4" height="5.4" rx="1" stroke="currentColor" stroke-width="1.3"/><rect x="1.5" y="9.1" width="5.4" height="5.4" rx="1" stroke="currentColor" stroke-width="1.3"/><rect x="9.1" y="9.1" width="5.4" height="5.4" rx="1" stroke="currentColor" stroke-width="1.3"/></svg>
                <span>Dashboard</span>
            </a>
            <a class="scan-cta" href="{{ route('scan') }}" @class(['active' => request()->routeIs('scan')])>
                <svg viewBox="0 0 16 16" fill="none"><path d="M1.5 5V3a1.5 1.5 0 0 1 1.5-1.5h2M11 1.5h2A1.5 1.5 0 0 1 14.5 3v2M14.5 11v2a1.5 1.5 0 0 1-1.5 1.5h-2M5 14.5H3A1.5 1.5 0 0 1 1.5 13v-2M2.5 8h11" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
                <span>RFID Scan</span>
            </a>
            <a href="{{ route('rfid.register') }}" @class(['active' => request()->routeIs('rfid.register')])>
                <svg viewBox="0 0 16 16" fill="none"><rect x="2" y="3.5" width="12" height="9" rx="1.5" stroke="currentColor" stroke-width="1.3"/><path d="M4.8 6.2h6.4M4.8 8.4h6.4M4.8 10.6h3.6" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/></svg>
                <span>Register cards</span>
            </a>
        </nav>

        <div class="nav-section">Records</div>
        <nav class="nav">
            <a href="{{ route('students.index') }}" @class(['active' => request()->routeIs('students.*')])>
                <svg viewBox="0 0 16 16" fill="none"><circle cx="8" cy="5" r="2.7" stroke="currentColor" stroke-width="1.3"/><path d="M2.5 14c.6-2.9 2.8-4.3 5.5-4.3S12.9 11.1 13.5 14" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/></svg>
                <span>Students</span>
            </a>
            <a href="{{ route('offenses.index') }}" @class(['active' => request()->routeIs('offenses.*')])>
                <svg viewBox="0 0 16 16" fill="none"><path d="M3 2h8.5L13.5 4v10H3V2z" stroke="currentColor" stroke-width="1.3" stroke-linejoin="round"/><path d="M5.5 6h5M5.5 9h5M5.5 12h3" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/></svg>
                <span>Offense Ledger</span>
            </a>
            @if (auth()->user()->isAdmin())
                <a href="{{ route('users.index') }}" @class(['active' => request()->routeIs('users.*')])>
                    <svg viewBox="0 0 16 16" fill="none"><circle cx="5.6" cy="5.4" r="2.2" stroke="currentColor" stroke-width="1.3"/><path d="M1.8 13.2c.5-2.2 2-3.3 3.8-3.3s3.3 1.1 3.8 3.3" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/><circle cx="11.4" cy="6" r="1.8" stroke="currentColor" stroke-width="1.2"/><path d="M10.6 9.9c1.8 0 3 1 3.5 2.8" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/></svg>
                    <span>Accounts</span>
                </a>
            @else
                <a href="#" onclick="event.preventDefault(); openRoleModal();" @class(['active' => request()->routeIs('users.*')])>
                    <svg viewBox="0 0 16 16" fill="none"><circle cx="5.6" cy="5.4" r="2.2" stroke="currentColor" stroke-width="1.3"/><path d="M1.8 13.2c.5-2.2 2-3.3 3.8-3.3s3.3 1.1 3.8 3.3" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/><circle cx="11.4" cy="6" r="1.8" stroke="currentColor" stroke-width="1.2"/><path d="M10.6 9.9c1.8 0 3 1 3.5 2.8" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/></svg>
                    <span>Accounts</span>
                </a>
            @endif
        </nav>

        <div class="sidebar-foot">
            <a href="{{ route('profile') }}" class="who" title="My profile" style="text-decoration: none; border-radius: var(--r-sm); padding: 4px; margin: -4px;">
                <div class="avatar">{{ collect(explode(' ', auth()->user()->name))->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->implode('') }}</div>
                <div>
                    <div class="who-name">{{ auth()->user()->name }}</div>
                    <div class="who-role">{{ \App\Models\User::roleLabel(auth()->user()->role) }}</div>
                </div>
            </a>
            <a href="{{ route('logout') }}" onclick="event.preventDefault(); document.getElementById('logout-form').submit();" style="display:flex; align-items:center; gap:8px; margin-top:12px; color:rgba(244,249,245,.7); font-size:12.5px; font-weight:500;">
                <svg width="14" height="14" viewBox="0 0 16 16" fill="none"><path d="M6 3H3.5A1.5 1.5 0 0 0 2 4.5v7A1.5 1.5 0 0 0 3.5 13H6M10.5 11l3-3-3-3M13.5 8H6" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/></svg>
                Sign out
            </a>
        </div>
    </aside>

    <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">@csrf</form>

    <div class="main">
        <header class="topbar">
            <div class="crumbs">
                @hasSection('crumbs')
                    @yield('crumbs')
                @else
                    <b>Dashboard</b>
                @endif
            </div>
            <div class="topbar-actions">
                @hasSection('topbar-actions')
                    @yield('topbar-actions')
                @else
                    <a class="btn btn-accent btn-sm" href="{{ route('scan') }}">
                        <svg viewBox="0 0 16 16" fill="none" width="15" height="15"><path d="M1.5 5V3a1.5 1.5 0 0 1 1.5-1.5h2M11 1.5h2A1.5 1.5 0 0 1 14.5 3v2M14.5 11v2a1.5 1.5 0 0 1-1.5 1.5h-2M5 14.5H3A1.5 1.5 0 0 1 1.5 13v-2M2.5 8h11" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
                        Scan RFID
                    </a>
                @endif
            </div>
        </header>

        <main class="content">
            @if (session('success'))
                <div class="toast" style="margin-bottom: 16px;">
                    <svg viewBox="0 0 16 16" fill="none"><path d="M2.5 8.5 6 12l7.5-8" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    {{ session('success') }}
                </div>
            @endif

            @if (session('error'))
                <div class="toast error" style="margin-bottom: 16px;">
                    <svg viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="6.5" stroke="currentColor" stroke-width="1.4"/><path d="M8 5v3.4M8 10.8v.4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
                    {{ session('error') }}
                </div>
            @endif

            @yield('content')
        </main>
    </div>
</div>

@stack('scripts')

@if (! auth()->user()->isAdmin())
    <div class="modal-back" id="role-modal" onclick="if (event.target === this) closeRoleModal();">
        <div class="modal" role="dialog" aria-modal="true" aria-labelledby="role-modal-title">
            <div class="modal-head">
                <h3 id="role-modal-title">Access restricted</h3>
            </div>
            <div class="modal-body">
                <div class="role-hint" style="margin-bottom: 12px;">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" style="vertical-align: -3px; margin-right: 6px;"><rect x="3" y="7" width="10" height="6.5" rx="1" stroke="currentColor" stroke-width="1.3"/><path d="M5.2 7V5.2a2.8 2.8 0 0 1 5.6 0V7" stroke="currentColor" stroke-width="1.3"/></svg>
                    <b>Your role is not fit to access the page.</b>
                </div>
                <p style="font-size: 13.5px; color: var(--fg-default); line-height: 1.6;">Please contact the <b style="color: var(--fg-strong);">MIS Programmer</b> for assistance.</p>
            </div>
            <div class="modal-foot">
                <button class="btn btn-primary" type="button" onclick="closeRoleModal();">Understood</button>
            </div>
        </div>
    </div>

    <script>
        function openRoleModal() {
            document.getElementById('role-modal').classList.add('open');
        }
        function closeRoleModal() {
            document.getElementById('role-modal').classList.remove('open');
        }
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') closeRoleModal();
        });
    </script>
@endif
</body>
</html>
