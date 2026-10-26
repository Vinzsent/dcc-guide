@extends('layouts.app')

@section('title', 'Enroll student — DCC Care')

@section('crumbs')
    <a href="{{ route('dashboard') }}">Dashboard</a> <span class="sep">/</span> <a href="{{ route('students.index') }}">Students</a> <span class="sep">/</span> <b>Enroll new</b>
@endsection

@section('content')
    <div class="page-head">
        <div>
            <h1>Enroll a student</h1>
            <p class="sub">Registry entry and RFID card assignment in one pass. Fields marked <span class="req" style="color:var(--red-700); font-weight:600;">*</span> are required.</p>
        </div>
    </div>

    <form class="formcard" method="POST" action="{{ route('students.store') }}">
        @csrf
        @include('students._form')

        <div class="savebar">
            <button class="btn btn-primary" type="submit">Save student</button>
            <a class="btn btn-ghost" href="{{ route('students.index') }}">Cancel</a>
            <span class="note">Saved records open in the student's profile immediately.</span>
        </div>
    </form>
@endsection
