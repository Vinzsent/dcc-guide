@extends('layouts.app')

@section('title', 'Edit student — DCC Care')

@section('crumbs')
    <a href="{{ route('dashboard') }}">Dashboard</a> <span class="sep">/</span> <a href="{{ route('students.index') }}">Students</a> <span class="sep">/</span> <b>{{ $student->full_name }}</b>
@endsection

@section('content')
    <div class="page-head">
        <div>
            <h1>Edit {{ $student->full_name }}</h1>
            <p class="sub">Changes appear immediately in the registry and on the student's case file.</p>
        </div>
    </div>

    <form class="formcard" method="POST" action="{{ route('students.update', $student) }}">
        @csrf
        @method('PUT')
        @include('students._form', ['student' => $student])

        <div class="savebar">
            <button class="btn btn-primary" type="submit">Save changes</button>
            <a class="btn btn-ghost" href="{{ route('students.show', $student) }}">Cancel</a>
        </div>
    </form>
@endsection
