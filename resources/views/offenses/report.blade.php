@extends('layouts.print')

@section('title', 'Case report '.$offense->reference_no.' — DCC Care')

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
            <span>Discipline Office · Official Case Record</span>
        </div>
        <div class="doc-type">
            <span class="kicker">Case Report</span>
            <span class="ref">{{ $offense->reference_no }}</span>
        </div>
    </div>

    <div class="grid-2col">
        <div class="panel">
            <div class="panel-title">Student</div>
            <div class="name-lg">{{ $offense->student->full_name }}</div>
            <div style="margin-top: 10px;">
                <div class="row"><span class="k">Student number</span><span class="v mono">{{ $offense->student->student_number }}</span></div>
                <div class="row"><span class="k">Gender</span><span class="v">{{ $offense->student->gender ? ucfirst($offense->student->gender) : 'Not set' }}</span></div>
                <div class="row"><span class="k">Program / Year</span><span class="v">{{ $offense->student->program_label }}</span></div>
                <div class="row"><span class="k">Enrollment status</span><span class="v">{{ ucfirst($offense->student->enrollmentLabel()) }}</span></div>
                @if ($offense->student->activeRfid)
                    <div class="row"><span class="k">RFID card</span><span class="v mono">{{ $offense->student->activeRfid->card_uid }}</span></div>
                @endif
            </div>
        </div>

        <div class="panel">
            <div class="panel-title">Incident</div>
            <div class="row"><span class="k">Offense</span><span class="v">{{ $offense->offenseType?->name ?? 'Other' }}</span></div>
            <div class="row">
                <span class="k">Severity</span>
                <span class="v">
                    @if ($offense->offenseType?->severity)
                        <span class="stamp {{ $offense->offenseType->severityStampClass() }}">{{ ucfirst($offense->offenseType->severity) }}</span>
                    @else
                        <span class="stamp muted">Unclassified</span>
                    @endif
                </span>
            </div>
            <div class="row"><span class="k">Date & time</span><span class="v">{{ $offense->incident_at->format('F j, Y · g:i a') }}</span></div>
            <div class="row"><span class="k">Location</span><span class="v">{{ $offense->location ?? '—' }}</span></div>
            <div class="row"><span class="k">Case status</span><span class="v">{{ ucfirst($offense->statusLabel()) }}</span></div>
        </div>
    </div>

    <div class="section">
        <h2>Description of the incident</h2>
        <p class="prose">{{ $offense->description }}</p>
        @if ($offense->witnesses)
            <p class="muted" style="margin-top: 8px;"><b style="font-weight:600; color: var(--ink-2);">Witnesses:</b> {{ $offense->witnesses }}</p>
        @endif
    </div>

    <div class="section">
        <h2>Follow-up actions</h2>
        @if ($offense->actions->isEmpty())
            <p class="empty-note">No follow-up actions have been recorded for this case yet.</p>
        @else
            @foreach ($offense->actions as $action)
                <div class="case-entry" style="border-bottom: 1px dashed var(--line-1);">
                    <div class="case-top">
                        <span class="stamp {{ in_array($action->action_type, ['hearing', 'parent_conference']) ? 'warn' : 'ok' }}">{{ ucwords(str_replace('_', ' ', $action->action_type)) }}</span>
                        <span class="case-meta">recorded {{ $action->created_at?->format('M j, Y') }} by {{ $action->recordedBy?->name ?? '—' }}</span>
                    </div>
                    @if ($action->notes)
                        <p class="desc">{{ $action->notes }}</p>
                    @endif
                </div>
            @endforeach
        @endif
    </div>

    <div class="signatures">
        <div class="sig">
            <div class="line"></div>
            <div class="cap">Prepared by</div>
            <div class="sub">{{ $offense->reportedBy?->name ?? $generatedBy->name }} · {{ now()->format('F j, Y') }}</div>
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
        <span>Case {{ $offense->reference_no }} · recorded {{ $offense->recorded_at?->format('M j, Y') }}</span>
    </div>
@endsection
