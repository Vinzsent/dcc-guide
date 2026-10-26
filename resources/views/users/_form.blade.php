<div class="field">
    <label for="name">Full name <span class="req">*</span></label>
    <input class="input @error('name') invalid @enderror" id="name" name="name" value="{{ old('name', $account->name ?? '') }}" required>
    @error('name')<p class="err">{{ $message }}</p>@enderror
</div>
<div class="field">
    <label for="username">Username <span class="req">*</span></label>
    <input class="input mono @error('username') invalid @enderror" id="username" name="username" value="{{ old('username', $account->username ?? '') }}" required>
    @error('username')<p class="err">{{ $message }}</p>@enderror
</div>
<div class="field">
    <label for="email">Email</label>
    <input class="input" type="email" id="email" name="email" value="{{ old('email', $account->email ?? '') }}" placeholder="Optional">
    @error('email')<p class="err">{{ $message }}</p>@enderror
</div>
<div class="field">
    <label for="role">Role <span class="req">*</span></label>
    <select class="select" id="role" name="role" required>
        @foreach ($roles as $role)
            <option value="{{ $role }}" @selected(old('role', $account->role ?? '') === $role)>{{ \App\Models\User::roleLabel($role) }}</option>
        @endforeach
    </select>
    <p class="help">Prefect of Discipline (POD) — full records, filing, and scanning · Coordinator — full records and filing · Scanner — gate scanning only · Administrator — accounts and everything above.</p>
    @error('role')<p class="err">{{ $message }}</p>@enderror
</div>
<div class="field">
    <label for="password">{{ isset($account) ? 'New password' : 'Password' }} @unless (isset($account))<span class="req">*</span>@endunless</label>
    <input class="input @error('password') invalid @enderror" type="password" id="password" name="password" placeholder="{{ isset($account) ? 'Leave blank to keep current password' : 'Minimum 8 characters' }}" @unless (isset($account)) required @endunless>
    @error('password')<p class="err">{{ $message }}</p>@enderror
</div>
