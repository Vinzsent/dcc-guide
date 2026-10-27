@extends('layouts.app')

@section('title', 'Unassign RFID cards — DCC Care')

@section('crumbs')
    <a href="{{ route('dashboard') }}">Dashboard</a> <span class="sep">/</span> <b>Unassign cards</b>
@endsection

@section('content')
    <div class="page-head">
        <div>
            <h1>Unassign RFID cards</h1>
            <p class="sub">Release a student's active card so it can be re-issued to someone else. The student keeps their record and can be scanned by ID number in the meantime.</p>
        </div>
        <div class="page-head-actions" style="text-align: right;">
            <div style="font: 600 30px/1 var(--font-display); color: var(--fg-strong);">{{ $assignedCount }} <span style="font-size: 16px; color: var(--fg-muted); font-family: var(--font-ui); font-weight: 500;">assigned</span></div>
            <div style="font-size: 12px; color: var(--fg-muted); margin-top: 4px;">{{ $withoutCards }} students still without cards</div>
        </div>
    </div>

    <section class="card table-card">
        <div class="card-head" style="flex-wrap: wrap;">
            <form class="toolbar" style="flex: 1;" method="GET" action="{{ route('rfid.unassign') }}">
                <div class="search">
                    <svg viewBox="0 0 16 16" fill="none"><circle cx="7" cy="7" r="4.4" stroke="currentColor" stroke-width="1.4"/><path d="M10.4 10.4 14 14" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
                    <input class="input" type="search" name="q" value="{{ request('q') }}" placeholder="Search name, student no., or RFID…">
                </div>
                <select class="select" name="level" style="width: auto;" onchange="this.form.submit()">
                    <option value="">All levels</option>
                    @foreach (['Grade School', 'Junior High School', 'Senior High School', 'College'] as $level)
                        <option value="{{ $level }}" @selected(request('level') === $level)>{{ $level }}</option>
                    @endforeach
                </select>
                @if (request()->filled('q') || request()->filled('level'))
                    <a class="btn-link" href="{{ route('rfid.unassign') }}">Clear</a>
                @endif
            </form>
        </div>

        @if ($students->isEmpty())
            <div class="empty">
                <div class="glyph"><svg width="18" height="18" viewBox="0 0 16 16" fill="none"><rect x="2" y="3.5" width="12" height="9" rx="1.5" stroke="currentColor" stroke-width="1.3"/><path d="M5.2 6.4l5.6 3.4M10.8 6.4 5.2 9.8" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/></svg></div>
                @if (request()->filled('q') || request()->filled('level'))
                    <b>No cards match</b>
                    Try a different search or level filter.
                @else
                    <b>No cards are assigned</b>
                    Every card is unassigned. Register cards to students from the registration station or their profile.
                @endif
            </div>
        @else
            <table class="ledger">
                <thead>
                    <tr>
                        <th>Student</th><th>RFID card</th><th>Level</th><th>Assigned</th><th style="text-align:right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($students as $student)
                        @php($card = $student->rfids->first())
                        <tr>
                            <td>
                                <span class="cell-student">
                                    <span class="avatar-sm">{{ $student->initials() }}</span>
                                    <span>
                                        <span class="name">{{ $student->full_name }}</span>
                                        <span class="sub">{{ $student->student_number }} · {{ $student->program_label }}</span>
                                    </span>
                                </span>
                            </td>
                            <td class="mono">{{ $card?->card_uid }}</td>
                            <td>{{ $student->education_level }}</td>
                            <td>
                                <span class="mono" style="font-size:13px;">{{ $card?->assigned_at?->format('M j, Y') ?? '—' }}</span>
                                <span class="sub" style="display:block;">by {{ $card?->assignedBy?->name ?? '—' }}</span>
                            </td>
                            <td class="cell-actions">
                                <form method="POST" action="{{ route('students.rfid.unassign', $student) }}" onsubmit="return confirm('Unassign card {{ $card?->card_uid }} from {{ $student->full_name }}? The card can be registered to another student afterwards.');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--red-700);">Unassign</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="table-foot">
                <span class="info">Showing {{ $students->firstItem() }}–{{ $students->lastItem() }} of {{ $students->total() }} assigned cards</span>
                <div class="pager">{{ $students->links() }}</div>
            </div>
        @endif
    </section>
@endsection
