@extends('layouts.app')

@section('title', 'Offense ledger — DCC Care')

@section('crumbs')
    <a href="{{ route('dashboard') }}">Dashboard</a> <span class="sep">/</span> <b>Offense ledger</b>
@endsection

@section('topbar-actions')
    <a class="btn btn-ghost btn-sm" href="{{ route('offenses.export', request()->only(['q', 'severity', 'status', 'year'])) }}">
        <svg width="14" height="14" viewBox="0 0 16 16" fill="none"><path d="M8 2v8M5 7l3 3 3-3M2.5 12.5h11" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
        Export CSV
    </a>
    <a class="btn btn-primary btn-sm" href="{{ route('offenses.create') }}">File an offense</a>
@endsection

@section('content')
    <div class="page-head">
        <div>
            <h1>Offense ledger</h1>
            <p class="sub">Every case on the record, newest first. {{ $monthCount }} filed this month.</p>
        </div>
    </div>

    <section class="card table-card">
        <div class="card-head" style="flex-wrap: wrap;">
            <form class="toolbar" style="flex: 1;" method="GET" action="{{ route('offenses.index') }}">
                <div class="search">
                    <svg viewBox="0 0 16 16" fill="none"><circle cx="7" cy="7" r="4.4" stroke="currentColor" stroke-width="1.4"/><path d="M10.4 10.4 14 14" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
                    <input class="input" type="search" name="q" value="{{ request('q') }}" placeholder="Search case no., student, or text…">
                </div>
                <select class="select" name="severity" style="width:auto;" onchange="this.form.submit()">
                    <option value="">All severities</option>
                    @foreach (['minor', 'major', 'critical'] as $severity)
                        <option value="{{ $severity }}" @selected(request('severity') === $severity)>{{ ucfirst($severity) }}</option>
                    @endforeach
                </select>
                <select class="select" name="status" style="width:auto;" onchange="this.form.submit()">
                    <option value="">All statuses</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status }}" @selected(request('status') === $status)>{{ str_replace('_', ' ', ucfirst($status)) }}</option>
                    @endforeach
                </select>
                <select class="select" name="year" style="width:auto;" onchange="this.form.submit()">
                    <option value="">All school years</option>
                    @foreach ($years as $y)
                        <option value="{{ $y }}" @selected(request('year') == $y)>SY {{ $y }}-{{ $y + 1 }}</option>
                    @endforeach
                </select>
                @if (request()->filled('q') || request()->filled('severity') || request()->filled('status') || request()->filled('year'))
                    <a class="btn-link" href="{{ route('offenses.index') }}">Clear</a>
                @endif
            </form>
        </div>

        @if ($offenses->isEmpty())
            <div class="empty">
                <div class="glyph"><svg width="18" height="18" viewBox="0 0 16 16" fill="none"><path d="M3 2h8.5L13.5 4v10H3V2z" stroke="currentColor" stroke-width="1.3" stroke-linejoin="round"/></svg></div>
                <b>The ledger is clear</b>
                No cases match — @if (request()->filled('q') || request()->filled('severity') || request()->filled('status'))try different filters.@else<a href="{{ route('offenses.create') }}">file the first case</a> when needed.@endif
            </div>
        @else
            <table class="ledger">
                <thead>
                    <tr><th>Case</th><th>Student</th><th>Offense</th><th>Severity</th><th>Date</th><th>Status</th><th style="text-align:right">Actions</th></tr>
                </thead>
                <tbody>
                    @foreach ($offenses as $offense)
                        <tr>
                            <td class="mono">{{ $offense->reference_no }}</td>
                            <td>
                                <span class="name">{{ $offense->student->full_name }}</span>
                                <span class="sub">{{ $offense->student->student_number }} · {{ $offense->student->program_label }}</span>
                            </td>
                            <td>{{ $offense->offenseType?->name ?? 'Other' }}</td>
                            <td><span class="{{ $offense->offenseType?->severityStampClass() ?? 'stamp muted' }}">{{ $offense->offenseType?->severity ? ucfirst($offense->offenseType->severity) : 'Other' }}</span></td>
                            <td class="mono">{{ $offense->incident_at->format('M j, Y') }}</td>
                            <td><span class="{{ $offense->statusStampClass() }}">{{ $offense->statusLabel() }}</span></td>
                            <td class="cell-actions">
                                <a class="btn-link" href="{{ route('offenses.report', $offense) }}" target="_blank">Report</a>
                                <a class="btn-link" href="{{ route('offenses.edit', $offense) }}">Edit</a>
                                <form style="display:inline;" method="POST" action="{{ route('offenses.destroy', $offense) }}" onsubmit="return confirm('Remove case {{ $offense->reference_no }} from the ledger? This also removes its follow-ups.');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn-link danger" style="border:none; background:none; cursor:pointer;">Remove</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="table-foot">
                <span class="info">Showing {{ $offenses->firstItem() }}–{{ $offenses->lastItem() }} of {{ $offenses->total() }} cases</span>
                <div class="pager">{{ $offenses->links() }}</div>
            </div>
        @endif
    </section>
@endsection
