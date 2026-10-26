@extends('layouts.app')

@section('title', $student->full_name.' — DCC Care')

@section('crumbs')
    <a href="{{ route('dashboard') }}">Dashboard</a> <span class="sep">/</span> <a href="{{ route('students.index') }}">Students</a> <span class="sep">/</span> <b>{{ $student->full_name }}</b>
@endsection

@section('topbar-actions')
    <span class="{{ $student->enrollmentStampClass() }}">{{ $student->enrollmentLabel() }}</span>
@endsection

@section('content')
    <div class="dossier-head">
        <img class="avatar-photo" src="{{ $student->avatarUrl() }}" alt="Photo of {{ $student->full_name }}" width="72" height="88">
        <div class="dossier-title">
            <h1>{{ $student->full_name }}</h1>
            <div class="idline">
                <span class="mono">{{ $student->student_number }}</span>
                <span>{{ $student->program_label }}</span>
                @if ($student->middle_name || $student->suffix)<span>·</span><span>{{ trim(($student->middle_name ?? '').' '.($student->suffix ?? '')) }}</span>@endif
            </div>
        </div>
        <div class="dossier-actions">
            <a class="btn btn-ghost" href="{{ route('students.report', $student) }}" target="_blank">
                <svg width="14" height="14" viewBox="0 0 16 16" fill="none"><path d="M4.5 2h5L12.5 5v9h-8V2z" stroke="currentColor" stroke-width="1.3" stroke-linejoin="round"/><path d="M5.8 8h4.4M5.8 10.6h4.4" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/></svg>
                Print case file
            </a>
            <a class="btn btn-ghost" href="{{ route('students.edit', $student) }}">Edit details</a>
            <a class="btn btn-primary" href="{{ route('offenses.create.for-student', $student) }}">File an offense</a>
        </div>
    </div>

    <div class="grid-prof">
        <section style="display: grid; gap: 18px;">
            <div class="card">
                <div class="card-head"><h2>Record</h2></div>
                <div class="card-body" style="padding-top: 6px;">
                    <div class="kvlist">
                        <div class="kv2"><span class="k">Education level</span><span class="v">{{ $student->education_level ?? '—' }}</span></div>
                        <div class="kv2"><span class="k">Gender</span><span class="v">{{ $student->gender ? ucfirst($student->gender) : 'Not set' }}</span></div>
                        <div class="kv2"><span class="k">Course / Grade</span><span class="v">{{ $student->course_or_grade ?? '—' }}</span></div>
                        <div class="kv2"><span class="k">Year & section</span><span class="v">{{ trim(($student->year_level ?? '').' '.$student->section) ?: '—' }}</span></div>
                        <div class="kv2"><span class="k">Enrollment status</span><span class="v"><span class="{{ $student->enrollmentStampClass() }}">{{ $student->enrollmentLabel() }}</span></span></div>
                        <div class="kv2"><span class="k">Registered</span><span class="v">{{ $student->created_at->format('M j, Y') }}</span></div>
                        <div class="kv2"><span class="k">Last update</span><span class="v">{{ $student->updated_at->format('M j, Y') }}@if ($student->updatedBy) · by {{ $student->updatedBy->name }}@endif</span></div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-head">
                    <h2>RFID card</h2>
                    @if ($student->activeRfid)<span class="stamp ok">Assigned</span>@else<span class="stamp muted">Not assigned</span>@endif
                </div>
                <div class="card-body">
                    @if ($student->activeRfid)
                        <div class="kv2" style="border:none; padding-top:0;">
                            <span class="k">Card number</span><span class="v mono">{{ $student->activeRfid->card_uid }}</span>
                        </div>
                        <p class="help" style="margin-bottom: 12px;">Assigned {{ $student->activeRfid->assigned_at?->format('M j, Y') }} by {{ $student->activeRfid->assignedBy?->name ?? '—' }}.</p>
                        <div style="display:flex; gap:8px;">
                            <a class="btn btn-ghost btn-sm" href="{{ route('scan', ['card' => $student->activeRfid->card_uid]) }}">Test at scanner</a>
                            <form method="POST" action="{{ route('students.rfid.unassign', $student) }}" onsubmit="return confirm('Unassign this card from {{ $student->full_name }}?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--red-700);">Unassign card</button>
                            </form>
                        </div>
                    @else
                        <p style="font-size:13.5px; color:var(--fg-default); margin-bottom:12px;">This student has no card yet — they won't be recognized at the gate scanner.</p>
                    @endif
                    <form method="POST" action="{{ route('students.rfid.assign', $student) }}" style="margin-top: 12px;">
                        @csrf
                        <label class="field" for="card_uid" style="margin-bottom:8px;">{{ $student->activeRfid ? 'Replace with card number' : 'Assign card number' }}</label>
                        <div style="display:flex; gap:8px;">
                            <input class="input mono" id="card_uid" name="card_uid" placeholder="Tap card on reader or type code" required>
                            <button class="btn btn-primary btn-sm" type="submit">Assign</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card danger-zone">
                <div class="card-head"><h2>Danger zone</h2></div>
                <div class="card-body">
                    <p style="font-size: 13px; color: var(--fg-default); margin-bottom: 12px;">Marking a student <b>withdrawn</b> (via Edit) hides them from gate scanning but keeps their case history. Deletion is permanent and only allowed for records with no cases.</p>
                    <form method="POST" action="{{ route('students.destroy', $student) }}" onsubmit="return confirm('Permanently delete {{ $student->full_name }} and unassign their card? This cannot be undone.');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger btn-sm">Delete record</button>
                    </form>
                </div>
            </div>
        </section>

        <section class="card table-card">
            <div class="card-head" style="flex-wrap: wrap;">
                <h2>Case history</h2>
                <div class="sevsum">
                    <div class="s"><b style="color:var(--green-600)">{{ $tallies['minor'] }}</b><span>Minor</span></div>
                    <div class="s"><b style="color:var(--gold-700)">{{ $tallies['major'] }}</b><span>Major</span></div>
                    <div class="s"><b style="color:var(--red-700)">{{ $tallies['critical'] }}</b><span>Critical</span></div>
                </div>
            </div>

            @if ($student->offenses->isEmpty())
                <div class="empty">
                    <div class="glyph"><svg width="18" height="18" viewBox="0 0 16 16" fill="none"><path d="M2.5 8.5 6 12l7.5-8" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
                    <b>A clean slate</b>
                    No offenses on record. <a href="{{ route('offenses.create.for-student', $student) }}">File the first one</a> if needed.
                </div>
            @else
                @foreach ($student->offenses as $offense)
                    <div class="case-detail" style="border-bottom: 1px solid var(--border-subtle);">
                        <div class="case-row">
                            <div class="case-ic {{ $offense->status === 'resolved' || $offense->status === 'closed' ? '' : '' }}" style="@if ($offense->offenseType?->severity === 'critical') background: var(--red-50); color: var(--red-700); border: 1px solid var(--red-100); @elseif ($offense->offenseType?->severity === 'major') background: var(--gold-50); color: var(--gold-700); border: 1px solid var(--gold-100); @endif">
                                <svg viewBox="0 0 16 16" fill="none"><path d="M3 2h8.5L13.5 4v10H3V2z" stroke="currentColor" stroke-width="1.3" stroke-linejoin="round"/><path d="M5.5 6h5M5.5 9h5" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/></svg>
                            </div>
                            <div style="flex: 1; min-width: 0;">
                                <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                                    <span class="mono" style="font-size:12.5px; color:var(--fg-muted);">{{ $offense->reference_no }}</span>
                                    <b style="font-weight:600; color:var(--fg-strong); font-size:13.5px;">{{ $offense->offenseType?->name ?? 'Other' }}</b>
                                    <span class="{{ $offense->offenseType?->severityStampClass() ?? 'stamp muted' }}">{{ $offense->offenseType?->severity ? ucfirst($offense->offenseType->severity) : 'Other' }}</span>
                                    <span class="{{ $offense->statusStampClass() }}">{{ $offense->statusLabel() }}</span>
                                </div>
                                <p style="margin-top:5px; font-size:13.5px; color:var(--fg-default); line-height:1.5;">{{ $offense->description }}</p>
                                <div style="margin-top:4px; font-size:12px; color:var(--fg-muted);">
                                    {{ $offense->incident_at->format('M j, Y · g:i a') }}@if ($offense->location) · {{ $offense->location }}@endif · filed by {{ $offense->reportedBy?->name ?? '—' }}
                                </div>
                                @if ($offense->actions->isNotEmpty())
                                    <div style="margin-top:8px; padding:8px 10px; background:var(--surface-sunken); border-radius:var(--r-sm); font-size:12.5px; color:var(--fg-default);">
                                        <b style="font-weight:600; color:var(--fg-strong);">Follow-up:</b>
                                        @foreach ($offense->actions as $action)
                                            <span>{{ ucfirst($action->action_type) }}</span>
                                            @if ($action->notes) — <span>{{ $action->notes }}</span>@endif
                                            @if (! $loop->last) · @endif
                                        @endforeach
                                    </div>
                                @endif
                                <div style="margin-top:8px; display:flex; gap:6px;">
                                    <a class="btn-link" href="{{ route('offenses.edit', $offense) }}">Edit case</a>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            @endif

            <div class="table-foot">
                <span class="info">{{ $student->offenses->count() }} case{{ $student->offenses->count() === 1 ? '' : 's' }} on record</span>
                <a class="btn-link" href="{{ route('offenses.create.for-student', $student) }}">Add a case to this record →</a>
            </div>
        </section>
    </div>
@endsection
