@extends('layouts.app')

@section('title', 'Edit profile — DCC Care')

@section('crumbs')
    <a href="{{ route('dashboard') }}">Dashboard</a> <span class="sep">/</span> <a href="{{ route('profile') }}">My profile</a> <span class="sep">/</span> <b>Edit</b>
@endsection

@section('content')
    <div class="page-head">
        <div>
            <h1>Edit your profile</h1>
            <p class="sub">Update how your name and contact details appear across DCC Care.</p>
        </div>
    </div>

    <form class="formcard" method="POST" action="{{ route('profile.update') }}">
        @csrf
        @method('PUT')

        <section class="card">
            <div class="card-head"><h2>Account details</h2></div>
            <div class="card-body">
                <div class="form-grid">
                    <div class="field span-2">
                        <label for="name">Full name <span class="req">*</span></label>
                        <input class="input @error('name') invalid @enderror" id="name" name="name" value="{{ old('name', $account->name) }}" required>
                        @error('name')<p class="err">{{ $message }}</p>@enderror
                    </div>
                    <div class="field">
                        <label for="username">Username <span class="req">*</span></label>
                        <input class="input mono @error('username') invalid @enderror" id="username" name="username" value="{{ old('username', $account->username) }}" required autocomplete="username">
                        @error('username')<p class="err">{{ $message }}</p>@enderror
                        <p class="help">This is what you use to sign in — choose carefully.</p>
                    </div>
                    <div class="field">
                        <label for="email">Email</label>
                        <input class="input @error('email') invalid @enderror" type="email" id="email" name="email" value="{{ old('email', $account->email) }}" placeholder="Optional">
                        @error('email')<p class="err">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div class="role-hint" style="margin-top: 6px;">
                    <svg width="15" height="15" viewBox="0 0 16 16" fill="none" style="vertical-align: -3px; margin-right: 6px;"><rect x="3" y="7" width="10" height="6.5" rx="1" stroke="currentColor" stroke-width="1.3"/><path d="M5.2 7V5.2a2.8 2.8 0 0 1 5.6 0V7" stroke="currentColor" stroke-width="1.3"/></svg>
                    Your role ({{ \App\Models\User::roleLabel($account->role) }}) and account status are managed by an administrator.
                </div>
            </div>
        </section>

        <div class="savebar">
            <button class="btn btn-primary" type="submit">Save changes</button>
            <a class="btn btn-ghost" href="{{ route('profile') }}">Cancel</a>
        </div>
    </form>
@endsection
