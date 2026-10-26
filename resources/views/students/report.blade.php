@extends('layouts.print')

@section('title', 'Case history — '.$student->full_name.' — DCC Care')

@section('content')
    <div class="doc-head">
        <div class="doc-mark">
            @if ($schoolLogo)
                <img src="{{ asset($schoolLogo) }}" alt="DCC logo">
            @else
                D
            @endif
        </div>
        <div class="who">
            <b>DCC Care</b>
            <span>Discipline Office · Case History Report</span>
        </div>
        <div class="doc-type">
            <span class="kicker">Student Case File</span>
            <span class="ref">{{ $student->student_number }}</span>
        </div>
    </div>

    <div class="grid-2col">
        <div class="panel">
            <div class="panel-title">Student</div>
            <div class="name-lg">{{ $student->full_name }}</div>
            <div style="margin-top: 10px;">
                <div class="row"><span class="k">Education level</span><span class="v">{{ $student->education_level ?? '—' }}</span></div>
                <div class="row"><span class="k">Gender</span><span class="v">{{ $student->gender ? ucfirst($student->gender) : 'Not set' }}</span></div>
                <div class="row"><span class="k">Program / Year</span><span class="v">{{ $student->program_label }}</span></div>
                <div class="row"><span class="k">Enrollment status</span><span class="v">{{ ucfirst($student->enrollmentLabel()) }}</span></div>
                <div class="row"><span class="k">RFID card</span><span class="v">{{ $student->activeRfid ? $student->activeRfid->card_uid : 'Not assigned' }}</span></div>
            </div>
        </div>

        <div class="panel">
            <div class="panel-title">Record summary</div>
            <div class="tallies" style="margin-top: 4px;">
                <div class="t"><b>{{ $student->offenses->count() }}</b><span>Total cases</span></div>
                <div class="t"><b>{{ $tallies['minor'] }}</b><span>Minor</span></div>
                <div class="t"><b>{{ $tallies['major'] }}</b><span>Major</span></div>
                <div class="t"><b>{{ $tallies['critical'] }}</b><span>Critical</span></div>
            </div>
            <p class="muted" style="margin-top: 14px;">This document reflects all cases on record as of {{ now()->format('F j, Y') }}.</p>
        </div>
    </div>

    <div class="section">
        <h2>Case ledger</h2>
        @if ($student->offenses->isEmpty())
            <p class="empty-note">A clean record — no cases have been filed for this student.</p>
        @else
            <table class="ledger">
                <thead>
                    <tr><th>Case</th><th>Date</th><th>Offense</th><th>Severity</th><th>Status</th></tr>
                </thead>
                <tbody>
                    @foreach ($student->offenses as $offense)
                        <tr>
                            <td class="mono">{{ $offense->reference_no }}</td>
                            <td class="mono">{{ $offense->incident_at->format('M j, Y') }}</td>
                            <td>{{ $offense->offenseType?->name ?? 'Other' }}</td>
                            <td>{{ $offense->offenseType?->severity ? ucfirst($offense->offenseType->severity) : 'Other' }}</td>
                            <td>{{ ucfirst($offense->statusLabel()) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    @if ($student->offenses->isNotEmpty())
        <div class="section">
            <h2>Case details</h2>
            @foreach ($student->offenses as $offense)
                <div class="case-entry">
                    <div class="case-top">
                        <span class="ref">{{ $offense->reference_no }}</span>
                        <b>{{ $offense->offenseType?->name ?? 'Other' }}</b>
                        @if ($offense->offenseType?->severity)
                            <span class="stamp {{ $offense->offenseType->severityStampClass() }}">{{ ucfirst($offense->offenseType->severity) }}</span>
                        @endif
                    </div>
                    <p class="desc">{{ $offense->description }}</p>
                    <div class="case-meta">
                        {{ $offense->incident_at->format('F j, Y · g:i a') }}@if ($offense->location) · {{ $offense->location }}@endif · filed by {{ $offense->reportedBy?->name ?? '—' }}@if ($offense->witnesses) · witnesses: {{ $offense->witnesses }}@endif
                    </div>
                    @if ($offense->actions->isNotEmpty())
                        <div class="followup">
                            <b style="font-weight:600; color: var(--ink-1);">Follow-up:</b>
                            @foreach ($offense->actions as $action)
                                {{ ucwords(str_replace('_', ' ', $action->action_type)) }}@if ($action->notes) — {{ $action->notes }}@endif @if (! $loop->last) · @endif
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    <div class="signatures">
        <div class="sig">
            <div class="line"></div>
            <div class="cap">Prepared by</div>
            <div class="sub">{{ $generatedBy->name }} · {{ now()->format('F j, Y') }}</div>
        </div>
        <div class="sig">
            <div class="line"></div>
            <div class="cap">Noted by</div>
            <div class="sub">Discipline Coordinator</div>
        </div>
        <div class="sig">
            <div class="line"></div>
            <div class="cap">Received by</div>
            <div class="sub">Parent / Guardian — signature over printed name</div>
        </div>
    </div>

    <div class="doc-foot">
        <span>Generated from DCC Care on {{ now()->format('F j, Y · g:i a') }} by {{ $generatedBy->name }}.</span>
        <span>Confidential — discipline records</span>
    </div>
@endsection
