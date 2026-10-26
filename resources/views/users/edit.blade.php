@extends('layouts.app')

@section('title', 'Edit account — DCC Care')

@section('crumbs')
    <a href="{{ route('dashboard') }}">Dashboard</a> <span class="sep">/</span> <a href="{{ route('users.index') }}">Accounts</a> <span class="sep">/</span> <b>{{ $account->name }}</b>
@endsection

@section('content')
    <div class="page-head">
        <div>
            <h1>Edit {{ $account->name }}</h1>
            <p class="sub">@if ($account->is_active)Active account.@else<span class="stamp muted" style="vertical-align:middle;">Deactivated</span> — this account cannot sign in.@endif</p>
        </div>
    </div>

    <form class="formcard" method="POST" action="{{ route('users.update', $account) }}">
        @csrf
        @method('PUT')
        <section class="card">
            <div class="card-body">
                @include('users._form', ['account' => $account, 'roles' => $roles])
            </div>
        </section>

        <div class="savebar">
            <button class="btn btn-primary" type="submit">Save changes</button>
            <a class="btn btn-ghost" href="{{ route('users.index') }}">Cancel</a>
        </div>
    </form>
@endsection
