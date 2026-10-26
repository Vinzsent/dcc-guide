@extends('layouts.app')

@section('title', 'Dashboard — DCC Care')

@section('content')
    <div class="page-head">
        <div>
            <h1>Good {{ now()->format('H') < 12 ? 'morning' : (now()->format('H') < 18 ? 'afternoon' : 'evening') }}, {{ explode(' ', $user->name)[0] }}</h1>
            <p class="sub">{{ now()->format('l, F j, Y') }} · {{ $monthCases }} case{{ $monthCases === 1 ? '' : 's' }} filed this month</p>
        </div>
        <div class="page-head-actions">
            <a class="btn btn-ghost" href="{{ route('students.create') }}">Add student</a>
            <a class="btn btn-primary" href="{{ route('offenses.create') }}">File an offense</a>
        </div>
    </div>

    <section class="stats">
        <div class="stat">
            <div class="k">College students</div>
            <div class="v">{{ $collegeCount }}</div>
            <div class="d">enrolled this school year</div>
        </div>
        <div class="stat gold">
            <div class="k">Cases · {{ now()->format('F') }}</div>
            <div class="v">{{ $monthCases }}</div>
            <div class="d">@if ($prevMonthCases > 0 && $monthCases > $prevMonthCases)<b class="down">+{{ $monthCases - $prevMonthCases }}</b> vs {{ now()->subMonth()->format('F') }}@elseif ($prevMonthCases > 0)<b>{{ $monthCases - $prevMonthCases }}</b> vs {{ now()->subMonth()->format('F') }}@else first month on record @endif</div>
        </div>
        <div class="stat">
            <div class="k">Open cases</div>
            <div class="v">{{ $pendingCount }}</div>
            <div class="d">awaiting action or review</div>
        </div>
        <div class="stat">
            <div class="k">RFID cards assigned</div>
            <div class="v">{{ $cardCount }}</div>
            <div class="d">@if ($collegeCount > 0){{ min(100, (int) round($cardCount / max($collegeCount, 1) * 100)) }}% of college@else — @endif</div>
        </div>
    </section>

    <div class="grid-2">
        <section class="card table-card">
            <div class="card-head">
                <h2>Latest in the ledger</h2>
                <a class="btn-link" href="{{ route('offenses.index') }}">Open full ledger →</a>
            </div>
            @if ($recentOffenses->isEmpty())
                <div class="empty">
                    <div class="glyph"><svg width="18" height="18" viewBox="0 0 16 16" fill="none"><path d="M3 2h8.5L13.5 4v10H3V2z" stroke="currentColor" stroke-width="1.3" stroke-linejoin="round"/></svg></div>
                    <b>No cases on the ledger yet</b>
                    A clean slate. <a href="{{ route('offenses.create') }}">File the first case</a> when needed.
                </div>
            @else
                <table class="ledger">
                    <thead>
                        <tr><th>Case</th><th>Student</th><th>Offense</th><th>Severity</th><th>Date</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($recentOffenses as $offense)
                            <tr class="rowlink" onclick="location='{{ route('students.show', $offense->student) }}'">
                                <td class="mono">{{ $offense->reference_no }}</td>
                                <td>
                                    <span class="name">{{ $offense->student->full_name }}</span>
                                    <span class="sub">{{ $offense->student->student_number }} · {{ $offense->student->program_label }}</span>
                                </td>
                                <td>{{ $offense->offenseType?->name ?? 'Other' }}</td>
                                <td><span class="{{ $offense->offenseType?->severityStampClass() ?? 'stamp muted' }}">{{ $offense->offenseType?->severity ? ucfirst($offense->offenseType->severity) : 'Other' }}</span></td>
                                <td class="mono">{{ $offense->incident_at->format('M j') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <div class="table-foot">
                    <span class="info">Latest {{ $recentOffenses->count() }} case{{ $recentOffenses->count() === 1 ? '' : 's' }}</span>
                    <div class="pager"><a href="{{ route('offenses.index') }}">View all</a></div>
                </div>
            @endif
        </section>

        <section style="display:grid; gap:18px;">
            <div class="card">
                <div class="card-head"><h2>Quick actions</h2></div>
                <div class="card-body quick">
                    <a class="gold" href="{{ route('scan') }}">
                        <span class="qic"><svg viewBox="0 0 16 16" fill="none"><path d="M1.5 5V3a1.5 1.5 0 0 1 1.5-1.5h2M11 1.5h2A1.5 1.5 0 0 1 14.5 3v2M14.5 11v2a1.5 1.5 0 0 1-1.5 1.5h-2M5 14.5H3A1.5 1.5 0 0 1 1.5 13v-2M2.5 8h11" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg></span>
                        <span><b>Scan an RFID card</b><span class="qsub">Gate scanning · manual entry fallback</span></span>
                    </a>
                    <a href="{{ route('offenses.create') }}">
                        <span class="qic"><svg viewBox="0 0 16 16" fill="none"><path d="M8 3v10M3 8h10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg></span>
                        <span><b>File an offense</b><span class="qsub">Open a new case in the ledger</span></span>
                    </a>
                    <a href="{{ route('students.create') }}">
                        <span class="qic"><svg viewBox="0 0 16 16" fill="none"><circle cx="8" cy="5" r="2.7" stroke="currentColor" stroke-width="1.3"/><path d="M2.5 14c.6-2.9 2.8-4.3 5.5-4.3S12.9 11.1 13.5 14" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/></svg></span>
                        <span><b>Enroll a student</b><span class="qsub">Registry + RFID card assignment</span></span>
                    </a>
                    @if ($user->isAdmin())
                        <a href="{{ route('users.index') }}">
                            <span class="qic"><svg viewBox="0 0 16 16" fill="none"><rect x="4.5" y="7" width="7" height="5.5" rx="1" stroke="currentColor" stroke-width="1.3"/><path d="M6 7V5.4a2 2 0 0 1 4 0V7" stroke="currentColor" stroke-width="1.3"/></svg></span>
                            <span><b>Manage accounts</b><span class="qsub">Staff access and roles</span></span>
                        </a>
                    @endif
                </div>
            </div>

            <div class="card">
                <div class="card-head"><h2>Open cases</h2><span class="meta">{{ $pendingCount }} open</span></div>
                <div class="card-body" style="padding-top:6px;">
                    @if ($pending->isEmpty())
                        <p style="color:var(--fg-muted); font-size:13.5px; padding: 8px 0;">Nothing awaiting action. The ledger is clear.</p>
                    @else
                        <ul class="tl">
                            @foreach ($pending as $case)
                                <li>
                                    <span class="tstamp">{{ $case->incident_at->format('M j') }}</span>
                                    <div class="what">
                                        <b>{{ $case->student->full_name }}</b> — {{ $case->offenseType?->name ?? 'Other' }}
                                        <div class="case">{{ $case->reference_no }} · {{ $case->statusLabel() }}</div>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        </section>
    </div>
@endsection
