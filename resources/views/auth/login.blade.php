<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="evergreen">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in — DCC Care</title>
    <script>try { var t = localStorage.getItem('dcc-theme'); if (t) document.documentElement.setAttribute('data-theme', t); } catch (e) {}</script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="signin-body">

    <aside class="brandpane">
        <div class="bp-mark">
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

        <div class="bp-lede">
            <h1>Every record, <em>on the record.</em></h1>
            <p>The discipline office's ledger — student registry, offense records, and RFID gate scanning in one place.</p>
        </div>

        <div class="bp-records" aria-hidden="true">
            <div><span>CASE&nbsp;OFF-{{ date('Y') }}-0003</span><b>Unauthorized absence</b></div>
            <div><span>CASE&nbsp;OFF-{{ date('Y') }}-0002</span><b>Dress code violation</b></div>
            <div><span>CASE&nbsp;OFF-{{ date('Y') }}-0001</span><b>Classroom disruption</b></div>
        </div>

        <div class="bp-foot">© {{ date('Y') }} DCC Care · Discipline Office Ledger</div>
    </aside>

    <main class="signpane">
        <div class="signcard">
            <h2>Sign in</h2>
            <p class="sub">Use your staff account. Accounts are issued by the administrator.</p>

            @if (session('success'))
                <div class="toast" style="margin-bottom: 18px;">
                    <svg viewBox="0 0 16 16" fill="none"><path d="M2.5 8.5 6 12l7.5-8" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    {{ session('success') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="toast error" style="margin-bottom: 18px;">
                    <svg viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="6.5" stroke="currentColor" stroke-width="1.4"/><path d="M8 5v3.4M8 10.8v.4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('login.post') }}">
                @csrf

                <div class="field">
                    <label for="username">Username</label>
                    <input class="input" id="username" name="username" type="text" value="{{ old('username') }}" placeholder="e.g. j.santos" required autofocus autocomplete="username">
                </div>

                <div class="field">
                    <label for="password">Password</label>
                    <div class="pw-wrap">
                        <input class="input" id="password" name="password" type="password" placeholder="Your password" required autocomplete="current-password">
                        <button type="button" class="reveal" data-reveal="password">Show</button>
                    </div>
                </div>

                <div class="between">
                    <label class="check"><input type="checkbox" name="remember"> Keep me signed in</label>
                </div>

                <button class="btn btn-primary btn-block" type="submit">Sign in</button>
            </form>

            <div class="signin-note">
                <b>Heads up:</b> students don't sign in here. Enrollment and RFID registration are done by staff on the
                <a href="{{ route('students.create') }}">Students</a> page after signing in.
            </div>
        </div>
    </main>

    <script>
        document.querySelectorAll('[data-reveal]').forEach((button) => {
            button.addEventListener('click', () => {
                const input = document.getElementById(button.dataset.reveal);
                const showing = input.type === 'text';
                input.type = showing ? 'password' : 'text';
                button.textContent = showing ? 'Show' : 'Hide';
            });
        });
    </script>
</body>
</html>
