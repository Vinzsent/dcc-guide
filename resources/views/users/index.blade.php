@extends('layouts.app')

@section('title', 'Accounts — DCC Care')

@section('crumbs')
    <a href="{{ route('dashboard') }}">Dashboard</a> <span class="sep">/</span> <b>Accounts</b>
@endsection

@section('topbar-actions')
    <a class="btn btn-primary btn-sm" href="{{ route('users.create') }}">Add account</a>
@endsection

@section('content')
    <div class="page-head">
        <div>
            <h1>Staff accounts</h1>
            <p class="sub">Only administrators can create or deactivate accounts. Coordinators can file and edit cases.</p>
        </div>
    </div>

    <section class="card table-card">
        <table class="ledger">
            <thead>
                <tr><th>Name</th><th>Username</th><th>Role</th><th>Status</th><th>Last change</th><th style="text-align:right">Actions</th></tr>
            </thead>
            <tbody>
                @foreach ($users as $account)
                    <tr>
                        <td>
                            <span class="name">{{ $account->name }}</span>
                            @if ($account->id === auth()->id())<span class="sub">that's you</span>@endif
                        </td>
                        <td class="mono">{{ $account->username }}</td>
                        <td>
                            @if ($account->role === 'administrator')
                                <span class="stamp major">Admin</span>
                            @elseif ($account->role === 'pod')
                                <span class="stamp ok">Prefect of Discipline</span>
                            @elseif ($account->role === 'coordinator')
                                <span class="stamp info">Coordinator</span>
                            @else
                                <span class="stamp muted">Scanner</span>
                            @endif
                        </td>
                        <td>
                            @if ($account->is_active)
                                <span class="stamp ok">Active</span>
                            @else
                                <span class="stamp muted">Deactivated</span>
                            @endif
                        </td>
                        <td>{{ $account->updated_at->format('M j, Y') }}</td>
                        <td class="cell-actions">
                            <a class="btn-link" href="{{ route('users.edit', $account) }}">Edit</a>
                            @unless ($account->id === auth()->id())
                                <form style="display:inline;" method="POST" action="{{ route('users.toggle', $account) }}"
                                      onsubmit="return confirm('{{ $account->is_active ? 'Deactivate' : 'Reactivate' }} account {{ $account->username }}?');">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="btn-link {{ $account->is_active ? 'danger' : '' }}" style="border:none; background:none; cursor:pointer;">
                                        {{ $account->is_active ? 'Deactivate' : 'Reactivate' }}
                                    </button>
                                </form>
                            @endunless
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div class="table-foot">
            <span class="info">{{ $users->where('is_active', true)->count() }} active · {{ $users->where('is_active', false)->count() }} deactivated</span>
        </div>
    </section>

    <div class="toast" style="margin-top: 16px;">
        <svg viewBox="0 0 16 16" fill="none"><path d="M2.5 8.5 6 12l7.5-8" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
        Deactivated accounts keep their case history but can no longer sign in.
    </div>
@endsection
