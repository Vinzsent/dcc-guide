@extends('layouts.app')

@section('title', 'Students — DCC Care')

@section('crumbs')
    <a href="{{ route('dashboard') }}">Dashboard</a> <span class="sep">/</span> <b>Students</b>
@endsection

@section('topbar-actions')
    <a class="btn btn-primary btn-sm" href="{{ route('students.create') }}">Add student</a>
@endsection

@section('content')
    <div class="page-head">
        <div>
            <h1>Student registry</h1>
            <p class="sub">{{ $enrolledCount }} college students enrolled · {{ $cardCount }} RFID cards assigned</p>
        </div>
    </div>

    <section class="card table-card">
        <div class="card-head" style="flex-wrap: wrap;">
            <form class="toolbar" style="flex: 1;" method="GET" action="{{ route('students.index') }}">
                <div class="search">
                    <svg viewBox="0 0 16 16" fill="none"><circle cx="7" cy="7" r="4.4" stroke="currentColor" stroke-width="1.4"/><path d="M10.4 10.4 14 14" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
                    <input class="input" type="search" name="q" value="{{ request('q') }}" placeholder="Search name, student no., or RFID…">
                </div>
                <select class="select" name="status" style="width:auto;" onchange="this.form.submit()">
                    <option value="">All statuses</option>
                    @foreach (['enrolled', 'pending_verification', 'inactive', 'graduated', 'withdrawn'] as $status)
                        <option value="{{ $status }}" @selected(request('status') === $status)>{{ str_replace('_', ' ', ucfirst($status)) }}</option>
                    @endforeach
                </select>
                <label class="chip @if (request()->boolean('no_rfid')) on @endif" style="cursor:pointer;">
                    <input type="checkbox" name="no_rfid" value="1" @checked(request()->boolean('no_rfid')) onchange="this.form.submit()" style="accent-color:currentColor;">
                    No RFID yet
                </label>
                @if (request()->filled('q') || request()->filled('status') || request()->boolean('no_rfid'))
                    <a class="btn-link" href="{{ route('students.index') }}">Clear</a>
                @endif
            </form>
        </div>

        @if ($students->isEmpty())
            <div class="empty">
                <div class="glyph"><svg width="18" height="18" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="5" r="2.7" stroke="currentColor" stroke-width="1.3"/><path d="M2.5 14c.6-2.9 2.8-4.3 5.5-4.3S12.9 11.1 13.5 14" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/></svg></div>
                <b>No college students match</b>
                Try a different search, or <a href="{{ route('students.create') }}">enroll a new student</a>.
            </div>
        @else
            <table class="ledger">
                <thead>
                    <tr>
                        <th>Student</th><th>Student no.</th><th>Program / Year</th>
                        <th>RFID card</th><th class="num">Cases</th><th>Status</th><th style="text-align:right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($students as $student)
                        <tr class="rowlink" onclick="location='{{ route('students.show', $student) }}'">
                            <td>
                                <span class="cell-student">
                                    <span class="avatar-sm">{{ $student->initials() }}</span>
                                    <span>
                                        <span class="name">{{ $student->full_name }}</span>
                                    </span>
                                </span>
                            </td>
                            <td class="mono">{{ $student->student_number }}</td>
                            <td>{{ $student->program_label }}</td>
                            <td>
                                @if ($student->activeRfid)
                                    <span class="mono" style="font-size:13px;">{{ $student->activeRfid->card_uid }}</span>
                                @else
                                    <span class="stamp muted">Not assigned</span>
                                @endif
                            </td>
                            <td class="num"><b>{{ $student->offenses_count }}</b></td>
                            <td><span class="{{ $student->enrollmentStampClass() }}">{{ $student->enrollmentLabel() }}</span></td>
                            <td class="cell-actions" onclick="event.stopPropagation();">
                                <a class="btn-link" href="{{ route('students.show', $student) }}">View</a>
                                <a class="btn-link" href="{{ route('students.edit', $student) }}">Edit</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="table-foot">
                <span class="info">Showing {{ $students->firstItem() }}–{{ $students->lastItem() }} of {{ $students->total() }} students</span>
                <div class="pager">{{ $students->links() }}</div>
            </div>
        @endif
    </section>
@endsection
