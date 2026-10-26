@extends('layouts.app')

@section('title', 'Add account — DCC Care')

@section('crumbs')
    <a href="{{ route('dashboard') }}">Dashboard</a> <span class="sep">/</span> <a href="{{ route('users.index') }}">Accounts</a> <span class="sep">/</span> <b>Add account</b>
@endsection

@section('content')
    <div class="page-head">
        <div>
            <h1>Add staff account</h1>
            <p class="sub">A temporary password is set here; the staff member can change it later.</p>
        </div>
    </div>

    <form class="formcard" method="POST" action="{{ route('users.store') }}">
        @csrf
        <section class="card">
            <div class="card-body">
                @include('users._form', ['roles' => $roles])
            </div>
        </section>

        <div class="savebar">
            <button class="btn btn-primary" type="submit">Create account</button>
            <a class="btn btn-ghost" href="{{ route('users.index') }}">Cancel</a>
        </div>
    </form>
@endsection
