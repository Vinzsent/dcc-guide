@extends('layouts.app')

@section('title', 'My profile — DCC Care')

@section('crumbs')
    <a href="{{ route('dashboard') }}">Dashboard</a> <span class="sep">/</span> <b>My profile</b>
@endsection

@section('content')
    <div class="dossier-head">
        <div class="avatar" style="width: 64px; height: 64px; font-size: 22px;">{{ collect(explode(' ', $account->name))->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->implode('') }}</div>
        <div class="dossier-title">
            <h1>{{ $account->name }}</h1>
            <div class="idline">
                <span class="mono">{{ $account->username }}</span>
                <span>{{ \App\Models\User::roleLabel($account->role) }}</span>
            </div>
        </div>
        <div class="dossier-actions">
            <a class="btn btn-ghost" href="{{ route('profile.edit') }}">Edit profile</a>
            @if ($account->is_active)
                <span class="stamp ok">Active</span>
            @else
                <span class="stamp muted">Deactivated</span>
            @endif
        </div>
    </div>

    <div class="grid-prof">
        <section class="card">
            <div class="card-head"><h2>Account details</h2></div>
            <div class="card-body" style="padding-top: 6px;">
                <div class="kvlist">
                    <div class="kv2"><span class="k">Full name</span><span class="v">{{ $account->name }}</span></div>
                    <div class="kv2"><span class="k">Username</span><span class="v mono">{{ $account->username }}</span></div>
                    <div class="kv2"><span class="k">Email</span><span class="v">{{ $account->email ?? '—' }}</span></div>
                    <div class="kv2"><span class="k">Role</span><span class="v">{{ \App\Models\User::roleLabel($account->role) }}</span></div>
                    <div class="kv2"><span class="k">Status</span><span class="v"><span class="{{ $account->is_active ? 'stamp ok' : 'stamp muted' }}">{{ $account->is_active ? 'Active' : 'Deactivated' }}</span></span></div>
                    <div class="kv2"><span class="k">Member since</span><span class="v">{{ $account->created_at->format('F j, Y') }}</span></div>
                    <div class="kv2"><span class="k">Last update</span><span class="v">{{ $account->updated_at->format('M j, Y · g:i a') }}</span></div>
                </div>
                <p class="help" style="margin-top: 12px;">Only your <b>role</b> and account status are managed by an administrator on the Accounts page.</p>
            </div>
        </section>

        <section class="card">
            <div class="card-head"><h2>Security</h2><span class="meta">change your password</span></div>
            <div class="card-body">
                <form method="POST" action="{{ route('profile.password') }}">
                    @csrf
                    @method('PUT')
                    <div class="field">
                        <label for="current_password">Current password <span class="req">*</span></label>
                        <input class="input @error('current_password') invalid @enderror" type="password" id="current_password" name="current_password" required autocomplete="current-password">
                        @error('current_password')<p class="err">{{ $message }}</p>@enderror
                    </div>
                    <div class="field">
                        <label for="password">New password <span class="req">*</span></label>
                        <input class="input @error('password') invalid @enderror" type="password" id="password" name="password" required autocomplete="new-password" placeholder="Minimum 8 characters">
                        @error('password')<p class="err">{{ $message }}</p>@enderror
                    </div>
                    <div class="field">
                        <label for="password_confirmation">Confirm new password <span class="req">*</span></label>
                        <input class="input" type="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password">
                    </div>
                    <button class="btn btn-primary" type="submit">Update password</button>
                    <p class="help" style="margin-top: 10px;">You stay signed in after changing your password.</p>
                </form>
            </div>
        </section>
    </div>

    <div class="card" style="margin-top: 18px;">
        <div class="card-head">
            <h2>Appearance</h2>
            <span class="meta" id="theme-saved" hidden>Palette saved ✓</span>
        </div>
        <div class="card-body">
            <p class="help" style="margin-bottom: 12px;">Pick the color palette that fits your personality — it applies instantly across every page and follows your account.</p>
            <div class="theme-grid" role="group" aria-label="Color palette">
                @foreach ([
                    'evergreen' => ['Evergreen & Brass', 'The classic ledger', '#14442C', '#D4A017'],
                    'ocean' => ['Ocean Blue', 'Calm and steady', '#16436B', '#56C7E8'],
                    'royal' => ['Royal Purple', 'Bold and creative', '#3B2470', '#E686BD'],
                    'rose' => ['Rose', 'Soft and charming', '#B01E52', '#F2A0BE'],
                    'crimson' => ['Crimson', 'Strong and decisive', '#6B1622', '#D4A017'],
                    'sunset' => ['Sunset', 'Warm and energetic', '#5C2A0E', '#F59E0B'],
                    'graphite' => ['Graphite', 'Clean and minimal', '#242D36', '#2DD4BF'],
                ] as $key => [$label, $desc, $primary, $accent])
                    <button type="button" class="theme-swatch @if ($userTheme === $key) active @endif" data-theme-choice="{{ $key }}" data-primary="{{ $primary }}" data-accent="{{ $accent }}">
                        <span class="theme-dots"><i style="background: {{ $primary }};"></i><i style="background: {{ $accent }};"></i></span>
                        <span class="theme-name">{{ $label }}</span>
                        <span class="theme-desc">{{ $desc }}</span>
                    </button>
                @endforeach
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
    let themeSaveTimer = null;

    document.querySelectorAll('[data-theme-choice]').forEach((button) => {
        button.addEventListener('click', async () => {
            const theme = button.dataset.themeChoice;

            // Live preview across the whole page
            document.documentElement.setAttribute('data-theme', theme);
            try { localStorage.setItem('dcc-theme', theme); } catch (storageError) {}

            document.querySelectorAll('[data-theme-choice]').forEach((b) => b.classList.toggle('active', b === button));

            try {
                const res = await fetch('{{ route('profile.theme') }}', {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                    body: JSON.stringify({ theme }),
                });
                if (res.ok) {
                    const saved = document.getElementById('theme-saved');
                    saved.hidden = false;
                    clearTimeout(themeSaveTimer);
                    themeSaveTimer = setTimeout(() => { saved.hidden = true; }, 2500);
                }
            } catch (fetchError) {
                // Preview stays applied; the palette will save next time online
            }
        });
    });
</script>
@endpush
